<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'hris';

    public function up(): void
    {
        // One row per attendance record. Every column is nullable: only the
        // fields someone actually overrides get a value, everything else
        // stays null and the calculation engine keeps using its own number.
        Schema::connection($this->connection)->create('attendance_pay_override', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('attendance_id')->unique();
            $table->integer('ot_minutes')->nullable();         // Manual OT adjustment, in minutes (added to calculated OT)
            $table->decimal('ndhrs', 8, 2)->nullable();        // ND hours (replaces the auto-detected value)
            $table->decimal('totalwo', 8, 2)->nullable();      // Total Hrs
            $table->decimal('reghrs', 8, 2)->nullable();       // Reg Hrs
            $table->decimal('ratday', 12, 2)->nullable();      // Rate/Day (base pay for the day)
            $table->decimal('regdaysot', 12, 2)->nullable();   // Reg Days OT Rate
            $table->decimal('ndrate', 12, 2)->nullable();      // ND Rate
            $table->decimal('spholiday', 12, 2)->nullable();   // Special Non-Working Holiday
            $table->decimal('spholidayot', 12, 2)->nullable(); // OT Special Holiday
            $table->decimal('regholidayot', 12, 2)->nullable();// OT Regular Holiday
            $table->decimal('regholiday', 12, 2)->nullable();  // Regular Holiday
            $table->decimal('totalpay', 12, 2)->nullable();    // Total Pay — overriding this wins outright
            $table->string('updated_by')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('attendance_pay_override');
    }
};