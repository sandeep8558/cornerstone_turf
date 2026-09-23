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
        Schema::create('turf_facilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turf_id')->constrained('turfs')->onDelete('cascade');
            $table->string('name');
            $table->enum('is_active', ['Yes', 'No'])->default('Yes');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('turf_facilities');
    }
};
