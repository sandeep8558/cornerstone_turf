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
        Schema::table('bookings', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                DB::statement("ALTER TABLE bookings MODIFY COLUMN status ENUM('Success', 'Failed', 'Pending', 'Cancelled') DEFAULT 'Pending'");
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                DB::statement("ALTER TABLE bookings MODIFY COLUMN status ENUM('Success', 'Failed', 'Pending') DEFAULT 'Pending'");
            }
        });
    }
};
