<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credentialing_cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_number')->unique();
            $table->foreignId('provider_id')->constrained('provider_details')->cascadeOnDelete();
            $table->foreignId('practice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('case_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('status_id')->constrained('statuses');
            $table->foreignId('delay_owner_id')->nullable()->constrained('delay_owners')->nullOnDelete();
            $table->foreignId('priority_id')->nullable()->constrained('priorities')->nullOnDelete();
            $table->foreignId('assigned_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('state')->nullable();
            $table->date('intake_date')->nullable();
            $table->date('submission_date')->nullable();
            $table->date('payer_follow_up_date')->nullable();
            $table->date('expected_completion_date')->nullable();
            $table->date('effective_date')->nullable();
            $table->date('termination_date')->nullable();
            $table->date('revalidation_due_date')->nullable();
            $table->date('next_follow_up_date')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_escalated')->default(false);
            $table->timestamp('last_action_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('case_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credentialing_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('status_id')->constrained('statuses');
            $table->foreignId('delay_owner_id')->nullable()->constrained('delay_owners')->nullOnDelete();
            $table->foreignId('changed_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('case_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credentialing_case_id')->constrained()->cascadeOnDelete();
            $table->string('activity_type');
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('summary');
            $table->string('reference_number')->nullable();
            $table->date('next_action_date')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_activities');
        Schema::dropIfExists('case_status_histories');
        Schema::dropIfExists('credentialing_cases');
    }
};
