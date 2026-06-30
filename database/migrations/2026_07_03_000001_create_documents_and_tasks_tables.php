<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained('provider_details')->nullOnDelete();
            $table->foreignId('practice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('credentialing_case_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->date('effective_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('state')->nullable();
            $table->string('status')->default('active');
            $table->foreignId('uploaded_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number')->default(1);
            $table->string('file_path');
            $table->string('original_name');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type')->nullable();
            $table->foreignId('uploaded_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestamps();
        });

        Schema::create('case_document_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credentialing_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_required')->default(true);
            $table->boolean('is_received')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['credentialing_case_id', 'document_type_id'], 'case_doc_type_unique');
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('credentialing_case_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained('provider_details')->nullOnDelete();
            $table->foreignId('assigned_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('priority_id')->nullable()->constrained('priorities')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('task_type')->default('manual');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('case_document_items');
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('documents');
    }
};
