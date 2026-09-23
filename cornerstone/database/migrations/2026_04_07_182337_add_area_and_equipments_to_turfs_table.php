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
        Schema::table('turfs', function (Blueprint $table) {
            $table->string('area')->nullable()->after('turf_type');
            $table->text('equipments')->nullable()->after('area');
        });
    }

    public function down(): void
    {
        Schema::table('turfs', function (Blueprint $table) {
            $table->dropColumn(['area', 'equipments']);
        });
    }
};
