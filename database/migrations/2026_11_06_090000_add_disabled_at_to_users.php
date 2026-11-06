<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Accounting accounts can be disabled from the NESI Admin site (approved 2026-11-06).
// Accounting database only (mysql connection), not HRIS.
return new class extends Migration
{
    protected $connection = 'mysql';

    public function up(): void
    {
        $schema = Schema::connection($this->connection);

        if (! $schema->hasColumn('users', 'disabled_at')) {
            $schema->table('users', function (Blueprint $table) {
                $table->timestamp('disabled_at')->nullable()->after('ga_secret');
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection($this->connection);

        if ($schema->hasColumn('users', 'disabled_at')) {
            $schema->table('users', function (Blueprint $table) {
                $table->dropColumn('disabled_at');
            });
        }
    }
};
