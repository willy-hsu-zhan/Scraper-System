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
        Schema::create('job_facebook', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('level');
            $table->string('language');
            $table->string('salary');
            $table->string('deadline');
            $table->date('date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_facebook');
    }
};
