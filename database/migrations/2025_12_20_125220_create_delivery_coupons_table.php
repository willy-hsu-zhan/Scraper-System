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
        Schema::create('delivery_coupons', function (Blueprint $table) {
            $table->id();
            $table->string('section_title');
            $table->string('platform')->index();
            $table->string('expire_date')->nullable();
            $table->text('content');
            $table->string('code')->nullable();
            $table->string('link')->nullable();

            // 去重依據
            $table->string('content_hash', 64)->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_coupons');
    }
};
