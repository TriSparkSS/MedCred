<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_case_links', function (Blueprint $table) {
            $table->id();
            $table->string('message_id', 500)->unique();
            $table->foreignId('credentialing_case_id')->constrained('credentialing_cases')->cascadeOnDelete();
            $table->timestamps();

            $table->index('credentialing_case_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_case_links');
    }
};
