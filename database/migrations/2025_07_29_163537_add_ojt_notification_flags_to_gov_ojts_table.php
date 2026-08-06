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
        Schema::table('gov_ojts', function (Blueprint $table) {
            $table->timestamp('notified_3days_before')->nullable()->after('registration_end_at');
            $table->timestamp('notified_1day_before')->nullable()->after('notified_3days_before');
            $table->timestamp('notified_1hour_before')->nullable()->after('notified_1day_before');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gov_ojts', function (Blueprint $table) {
            $table->dropColumn([
                'notified_3days_before',
                'notified_1day_before',
                'notified_1hour_before',
            ]);
        });
    }
};
