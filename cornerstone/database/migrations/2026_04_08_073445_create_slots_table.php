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
        Schema::create('slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turf_id')->constrained()->onDelete('cascade');
            $table->foreignId('slot_category_id')->constrained()->onDelete('cascade');
            $table->time('from');
            $table->time('to');
            $table->integer('minutes');
            $table->decimal('mon_amount', 10, 2);
            $table->decimal('tue_amount', 10, 2);
            $table->decimal('wed_amount', 10, 2);
            $table->decimal('thu_amount', 10, 2);
            $table->decimal('fri_amount', 10, 2);
            $table->decimal('sat_amount', 10, 2);
            $table->decimal('sun_amount', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slots');
    }
};
