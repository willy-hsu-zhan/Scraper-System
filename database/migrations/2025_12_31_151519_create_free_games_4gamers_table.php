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
        Schema::create('free_games_4gamers', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('title_hash')->unique();
            $table->text('description')->nullable();
            $table->string('link');
            $table->date('publish_date')->nullable();
            $table->boolean('is_push')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('free_games_4gamers');
    }
};
