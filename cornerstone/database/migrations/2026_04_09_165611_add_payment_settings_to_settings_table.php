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
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('is_part_payment_active')->default(false)->after('is_refund_active');
            $table->decimal('min_part_payment', 8, 2)->default(500)->after('is_part_payment_active');
            $table->boolean('is_pay_at_location_active')->default(false)->after('min_part_payment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['is_part_payment_active', 'min_part_payment', 'is_pay_at_location_active']);
        });
    }
};
