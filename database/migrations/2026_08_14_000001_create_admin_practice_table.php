<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_practice', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->foreignId('practice_id')->constrained('practices')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['admin_id', 'practice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_practice');
    }
};
