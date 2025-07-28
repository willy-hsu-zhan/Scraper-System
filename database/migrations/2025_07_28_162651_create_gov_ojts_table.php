<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('gov_ojts', function (Blueprint $table) {
            $table->id();
            $table->string('training_unit_url')->unique();
            $table->string('course_code')->unique();
            $table->string('course_name');
            $table->string('training_count')->nullable();
            $table->string('training_hours')->nullable();
            $table->string('student_cost')->nullable();
            $table->string('government_cost')->nullable();
            $table->string('training_location')->nullable();
            $table->string('registration_start_at')->nullable();
            $table->string('registration_end_at')->nullable();
            $table->string('enrollment_status')->nullable();
            $table->string('scheduled_start_date')->nullable();
            $table->string('scheduled_end_date')->nullable();
            $table->string('payment_method')->nullable();
            $table->dateTime('notified_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gov_ojts');
    }
};
