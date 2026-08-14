<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payer_document_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_type_id')->constrained()->cascadeOnDelete();
            $table->string('state')->nullable();
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            $table->unique(['payer_id', 'document_type_id', 'state'], 'payer_doc_state_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payer_document_requirements');
    }
};
