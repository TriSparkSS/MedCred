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
        Schema::create('provider_practice', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('provider_details')->onDelete('cascade');
            $table->foreignId('practice_id')->constrained('practices')->onDelete('cascade');
            $table->boolean('primary_flag')->default(false);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();

            $table->unique(['provider_id', 'practice_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_practice');
    }
};
