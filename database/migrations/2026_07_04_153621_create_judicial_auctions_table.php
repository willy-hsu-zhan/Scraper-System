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
        Schema::create('judicial_auctions', function (Blueprint $table) {
            $table->id();

            // 法院
            $table->string('court')->comment('法院');

            // 案號
            $table->string('case_no')->index()->comment('案號');

            // 拍賣日期 + 拍次
            $table->string('auction_date')->comment('拍賣日期與拍次');

            // 地區
            $table->string('area')->nullable()->comment('地區');

            // 地址
            $table->string('address')->comment('地址');

            // 價格
            $table->integer('price')->comment('底價');

            // PDF
            $table->text('pdf_url')->nullable()->comment('PDF網址');

            $table->timestamps();

            // 同一法院同一案號通常唯一
            $table->unique(['court', 'case_no']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('judicial_auctions');
    }
};
