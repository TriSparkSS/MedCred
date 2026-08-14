<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('template_key')->unique();
            $table->string('subject');
            $table->text('body');
            $table->string('category')->default('general');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('email_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credentialing_case_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('notification_template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sent_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('direction')->default('outbound');
            $table->string('thread_id')->nullable()->index();
            $table->string('external_message_id')->nullable()->index();
            $table->string('from_address');
            $table->string('to_address');
            $table->string('cc_address')->nullable();
            $table->string('subject');
            $table->longText('body');
            $table->string('status')->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('email_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->string('file_path');
            $table->string('original_name');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type')->nullable();
            $table->timestamps();
        });

        Schema::create('sla_rules', function (Blueprint $table) {
            $table->id();
            $table->string('rule_key')->unique();
            $table->string('name');
            $table->unsignedInteger('days');
            $table->boolean('business_days_only')->default(true);
            $table->string('applies_to');
            $table->string('action')->default('reminder');
            $table->foreignId('notification_template_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('case_sla_timers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credentialing_case_id')->constrained()->cascadeOnDelete();
            $table->string('rule_key');
            $table->timestamp('started_at');
            $table->timestamp('due_at');
            $table->timestamp('triggered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['credentialing_case_id', 'rule_key', 'status']);
        });

        Schema::create('case_sla_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credentialing_case_id')->constrained()->cascadeOnDelete();
            $table->string('rule_key');
            $table->string('event_type');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('delay_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credentialing_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('previous_delay_owner_id')->nullable()->constrained('delay_owners')->nullOnDelete();
            $table->foreignId('new_delay_owner_id')->nullable()->constrained('delay_owners')->nullOnDelete();
            $table->text('reason');
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->string('action');
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('delay_overrides');
        Schema::dropIfExists('case_sla_events');
        Schema::dropIfExists('case_sla_timers');
        Schema::dropIfExists('sla_rules');
        Schema::dropIfExists('email_attachments');
        Schema::dropIfExists('email_messages');
        Schema::dropIfExists('notification_templates');
    }
};
