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
        Schema::create('birthday_discounts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('title_hash')->unique();
            $table->string('link');
            $table->date('expire_date')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_expired')->default(false);
            $table->boolean('is_push')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('birthday_discounts');
    }
};
