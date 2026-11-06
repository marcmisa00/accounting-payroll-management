#!/usr/bin/env bash
#
# Copy the LIVE HRIS database down into your LOCAL Laragon MySQL.
#
# Run it from the Laragon Terminal (Git Bash), in the project root:
#     bash scripts/sync-hris-from-live.sh
#
# SAFETY: what this script does to the LIVE server
#   - It runs ONLY `mysqldump --single-transaction`, which READS data.
#     No INSERT/UPDATE/DELETE/DROP is ever sent to the live database.
#   - --single-transaction takes a consistent snapshot without locking tables,
#     so HRIS and Accounting keep working while it runs.
#   - Nothing is written on the server: the dump streams straight to your PC.
#   - The live DB password is typed at a prompt and passed over SSH stdin,
#     never on a command line, in this file, or in a log.
#
# SAFETY: what it does on YOUR PC
#   - Imports ONLY into MySQL on 127.0.0.1 (hard-coded below, not read from .env).
#   - Refuses to run unless .env's HRIS_DB_HOST is 127.0.0.1 / localhost and
#     HRIS_DATABASE_URL is empty, so the app can't be left pointing at live data.
#   - The dump is made WITHOUT --databases, so it has no CREATE DATABASE / USE
#     lines and can only land in the local database named below.
#   - Each import REPLACES the tables in the local database (that's the point).
#
# Optional settings (environment variables), otherwise you are prompted:
#   LIVE_SSH      ssh target, e.g. user@acct.nesistaff.com
#   LIVE_DB_NAME  live HRIS database name
#   LIVE_DB_USER  live MySQL user (needs SELECT, SHOW VIEW, TRIGGER, LOCK TABLES)
#   LOCAL_DB_USER local MySQL user   (default: root)
#   LOCAL_DB_PASS local MySQL password (default: empty, Laragon default)
#   BACKUP_DIR    where dumps are kept (default: /c/laragon/backup, outside the repo)

set -euo pipefail

cd "$(dirname "$0")/.."

die() { echo "ERROR: $*" >&2; exit 1; }

# ---------- 0. Only ever run on a Windows dev PC (Laragon / Git Bash) ----------
# On the live VPS, 127.0.0.1 IS the live database, so this script must never
# run there. The VPS is Linux; Git Bash on Windows reports MINGW/MSYS/CYGWIN.
case "$(uname -s)" in
    MINGW*|MSYS*|CYGWIN*) ;;
    *) die "This script only runs on a Windows dev PC (Laragon). Refusing to run on $(uname -s): here 127.0.0.1 could be the LIVE database." ;;
esac

# ---------- 1. Check the local .env points to LOCAL databases ----------

[ -f .env ] || die ".env not found in $(pwd)"

env_value() {
    # Read KEY=value from .env, strip quotes and Windows line endings.
    # A missing key is fine (returns empty) — don't let grep's "no match" abort the script.
    { grep -E "^$1=" .env || true; } | tail -n 1 | cut -d= -f2- | tr -d '\r' | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//"
}

HRIS_HOST="$(env_value HRIS_DB_HOST)"
HRIS_URL="$(env_value HRIS_DATABASE_URL)"
LOCAL_DB_NAME="$(env_value HRIS_DB_DATABASE)"

case "$HRIS_HOST" in
    127.0.0.1|localhost) ;;
    *) die "HRIS_DB_HOST in .env is '$HRIS_HOST', not 127.0.0.1/localhost.
       Point .env at your local MySQL first (see the steps in the chat), then re-run.
       Nothing was copied." ;;
esac

[ -z "$HRIS_URL" ] || die "HRIS_DATABASE_URL is set in .env; it overrides HRIS_DB_HOST. Remove it first."
[ -n "$LOCAL_DB_NAME" ] || die "HRIS_DB_DATABASE is empty in .env. Set it to your local name, e.g. hris_local."

DB_HOST_VALUE="$(env_value DB_HOST)"
case "$DB_HOST_VALUE" in
    127.0.0.1|localhost|"") ;;
    *) echo "WARNING: DB_HOST in .env is '$DB_HOST_VALUE' (sessions/cache DB), not local." >&2 ;;
esac

# ---------- 2. Gather settings ----------

command -v mysql >/dev/null || die "mysql not found. Run this from the Laragon Terminal."
command -v ssh   >/dev/null || die "ssh not found."

: "${LIVE_SSH:=}";      [ -n "$LIVE_SSH" ]     || read -r -p "SSH target (user@host): " LIVE_SSH
: "${LIVE_DB_NAME:=}";  [ -n "$LIVE_DB_NAME" ] || read -r -p "LIVE HRIS database name: " LIVE_DB_NAME
: "${LIVE_DB_USER:=}";  [ -n "$LIVE_DB_USER" ] || read -r -p "LIVE MySQL user: " LIVE_DB_USER
read -r -s -p "LIVE MySQL password (hidden): " LIVE_DB_PASS; echo

LOCAL_DB_USER="${LOCAL_DB_USER:-root}"
LOCAL_DB_PASS="${LOCAL_DB_PASS:-}"
BACKUP_DIR="${BACKUP_DIR:-/c/laragon/backup}"

# Only allow plain names, so nothing odd can be injected into the remote command.
[[ "$LIVE_DB_NAME"  =~ ^[A-Za-z0-9_]+$ ]] || die "Invalid live database name."
[[ "$LIVE_DB_USER"  =~ ^[A-Za-z0-9_.@-]+$ ]] || die "Invalid live MySQL user."
[[ "$LOCAL_DB_NAME" =~ ^[A-Za-z0-9_]+$ ]] || die "Invalid local database name in .env."

mkdir -p "$BACKUP_DIR"
DUMP="$BACKUP_DIR/hris_live_$(date +%Y-%m-%d_%H%M).sql.gz"

echo
echo "  FROM (read-only): $LIVE_SSH  ->  database '$LIVE_DB_NAME'"
echo "  TO   (replaced) : 127.0.0.1  ->  database '$LOCAL_DB_NAME'"
echo "  Dump file       : $DUMP"
echo
read -r -p "Type YES to continue: " CONFIRM
[ "$CONFIRM" = "YES" ] || die "Cancelled. Nothing was copied."

# ---------- 3. Dump from live (READ-ONLY) ----------

echo "Dumping live database (read-only)..."

# The remote script is sent over stdin: the password never appears in any
# process list or command line. The remote side only runs mysqldump.
{
    printf 'DB_PASS=%q\n' "$LIVE_DB_PASS"
    printf 'DB_USER=%q\n' "$LIVE_DB_USER"
    printf 'DB_NAME=%q\n' "$LIVE_DB_NAME"
    cat <<'REMOTE'
set -o pipefail
mysqldump \
    --defaults-extra-file=<(printf '[client]\npassword=%s\n' "$DB_PASS") \
    -u "$DB_USER" \
    --single-transaction --quick --no-tablespaces \
    --skip-lock-tables --triggers --routines --set-gtid-purged=OFF \
    "$DB_NAME" 2>/tmp/.hris_dump_err_$$ | gzip -c
status=$?
if [ $status -ne 0 ]; then
    # --set-gtid-purged is MySQL/Percona-only; retry without it if unsupported.
    if grep -q "set-gtid-purged" /tmp/.hris_dump_err_$$; then
        rm -f /tmp/.hris_dump_err_$$
        mysqldump \
            --defaults-extra-file=<(printf '[client]\npassword=%s\n' "$DB_PASS") \
            -u "$DB_USER" \
            --single-transaction --quick --no-tablespaces \
            --skip-lock-tables --triggers --routines \
            "$DB_NAME" | gzip -c
        exit $?
    fi
    cat /tmp/.hris_dump_err_$$ >&2
fi
rm -f /tmp/.hris_dump_err_$$
exit $status
REMOTE
} | ssh -T "$LIVE_SSH" 'bash -s' > "$DUMP" || { rm -f "$DUMP"; die "Dump failed. Local database was NOT touched."; }

unset LIVE_DB_PASS

# Sanity check: a real dump is not tiny and ends with "Dump completed".
gzip -t "$DUMP" 2>/dev/null || { rm -f "$DUMP"; die "Downloaded file is corrupt. Local database was NOT touched."; }
gzip -dc "$DUMP" | tail -n 1 | grep -q "Dump completed" || { rm -f "$DUMP"; die "Dump looks incomplete. Local database was NOT touched."; }

echo "Downloaded: $DUMP ($(du -h "$DUMP" | cut -f1))"

# ---------- 4. Import into LOCAL MySQL only ----------

LOCAL_ARGS=(-h 127.0.0.1 -u "$LOCAL_DB_USER")
[ -n "$LOCAL_DB_PASS" ] && LOCAL_ARGS+=("-p$LOCAL_DB_PASS")

echo "Importing into LOCAL 127.0.0.1 / $LOCAL_DB_NAME ..."
mysql "${LOCAL_ARGS[@]}" -e "CREATE DATABASE IF NOT EXISTS \`$LOCAL_DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
gzip -dc "$DUMP" | mysql "${LOCAL_ARGS[@]}" "$LOCAL_DB_NAME"

TABLES="$(mysql "${LOCAL_ARGS[@]}" -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$LOCAL_DB_NAME';")"
echo "Imported $TABLES tables into local '$LOCAL_DB_NAME'."

# ---------- 5. Refresh Laravel config ----------

php artisan config:clear

echo
echo "Done. Live database was only read. Dump kept at: $DUMP"
echo "Delete old dumps when you no longer need them (they contain employee and bank data)."
