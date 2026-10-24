<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'hris';

    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasColumn('payroll_details', 'salary_rate')) {
            Schema::connection($this->connection)->table('payroll_details', function (Blueprint $table) {
                $table->decimal('salary_rate', 12, 2)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection($this->connection)->hasColumn('payroll_details', 'salary_rate')) {
            Schema::connection($this->connection)->table('payroll_details', function (Blueprint $table) {
                $table->dropColumn('salary_rate');
            });
        }
    }
};
