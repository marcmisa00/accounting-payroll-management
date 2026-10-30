<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Standalone Accounting login: accounts live in the Accounting (mysql) users table, not HRIS
return new class extends Migration
{
    protected $connection = 'mysql';

    public function up(): void
    {
        $schema = Schema::connection($this->connection);

        if (! $schema->hasColumn('users', 'idno')) {
            $schema->table('users', function (Blueprint $table) {
                $table->string('idno', 20)->nullable()->unique()->after('id');
            });
        }

        if (! $schema->hasColumn('users', 'username')) {
            $schema->table('users', function (Blueprint $table) {
                // Used to sign in; the ID number is only checked against HRIS access
                $table->string('username', 30)->nullable()->unique()->after('idno');
            });
        }

        if (! $schema->hasColumn('users', 'ga_secret')) {
            $schema->table('users', function (Blueprint $table) {
                // Stored with Laravel's encrypted cast (APP_KEY)
                $table->text('ga_secret')->nullable()->after('password');
            });
        }

        // Most HRIS employees have no email on file
        $schema->table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        $schema = Schema::connection($this->connection);

        if ($schema->hasColumn('users', 'ga_secret')) {
            $schema->table('users', function (Blueprint $table) {
                $table->dropColumn('ga_secret');
            });
        }

        if ($schema->hasColumn('users', 'username')) {
            $schema->table('users', function (Blueprint $table) {
                $table->dropUnique(['username']);
                $table->dropColumn('username');
            });
        }

        if ($schema->hasColumn('users', 'idno')) {
            $schema->table('users', function (Blueprint $table) {
                $table->dropUnique(['idno']);
                $table->dropColumn('idno');
            });
        }

        // Only restore NOT NULL when no account is missing an email
        if (! DB::connection($this->connection)->table('users')->whereNull('email')->exists()) {
            $schema->table('users', function (Blueprint $table) {
                $table->string('email')->nullable(false)->change();
            });
        }
    }
};
