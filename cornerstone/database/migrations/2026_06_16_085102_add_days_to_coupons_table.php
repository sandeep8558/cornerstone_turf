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
        Schema::table('coupons', function (Blueprint $table) {
            $table->boolean('mon')->default(true)->after('is_active');
            $table->boolean('tue')->default(true)->after('mon');
            $table->boolean('wed')->default(true)->after('tue');
            $table->boolean('thu')->default(true)->after('wed');
            $table->boolean('fri')->default(true)->after('thu');
            $table->boolean('sat')->default(true)->after('fri');
            $table->boolean('sun')->default(true)->after('sat');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun']);
        });
    }
};
