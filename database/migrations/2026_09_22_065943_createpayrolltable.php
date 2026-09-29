<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'hris';

    public function up(): void
    {
        Schema::connection($this->connection)->create('payroll', function (Blueprint $table) {
            $table->id();
            $table->date('periodfrom')->nullable();
            $table->date('periodto')->nullable();
            $table->string('period', 100)->nullable();
            $table->integer('days')->nullable();
            $table->string('addedby', 100)->nullable();
            $table->dateTime('addeddatetime')->nullable();
            $table->string('updatedby', 100)->nullable();
            $table->dateTime('updateddatetime')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('payroll');
    }
};