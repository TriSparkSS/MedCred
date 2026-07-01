<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            if (! Schema::hasColumn('tasks', 'status')) {
                $table->string('status', 30)->default('open')->after('task_type');
            }
            if (! Schema::hasColumn('tasks', 'follow_up_date')) {
                $table->date('follow_up_date')->nullable()->after('due_date');
            }
            if (! Schema::hasColumn('tasks', 'is_escalated')) {
                $table->boolean('is_escalated')->default(false)->after('status');
            }
            if (! Schema::hasColumn('tasks', 'assigned_by_admin_id')) {
                $table->foreignId('assigned_by_admin_id')->nullable()->after('assigned_admin_id')->constrained('admins')->nullOnDelete();
            }
            if (! Schema::hasColumn('tasks', 'escalated_at')) {
                $table->timestamp('escalated_at')->nullable()->after('is_escalated');
            }
            if (! Schema::hasColumn('tasks', 'escalated_by_admin_id')) {
                $table->foreignId('escalated_by_admin_id')->nullable()->after('escalated_at')->constrained('admins')->nullOnDelete();
            }
            if (! Schema::hasColumn('tasks', 'payer_id')) {
                $table->foreignId('payer_id')->nullable()->after('provider_id')->constrained('payers')->nullOnDelete();
            }
            if (! Schema::hasColumn('tasks', 'automation_key')) {
                $table->string('automation_key')->nullable()->after('task_type');
            }
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->index('assigned_admin_id');
            $table->index('credentialing_case_id');
            $table->index('provider_id');
            $table->index('due_date');
            $table->index('status');
            $table->index('completed_at');
            $table->index(['automation_key', 'credentialing_case_id']);
        });

        if (! Schema::hasTable('task_notes')) {
            Schema::create('task_notes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('task_id')->constrained()->cascadeOnDelete();
                $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
                $table->text('body');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('task_attachments')) {
            Schema::create('task_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('task_id')->constrained()->cascadeOnDelete();
                $table->string('file_path');
                $table->string('original_name');
                $table->foreignId('uploaded_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('task_activities')) {
            Schema::create('task_activities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('task_id')->constrained()->cascadeOnDelete();
                $table->string('activity_type', 50);
                $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
                $table->string('summary');
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('admin_notifications')) {
            Schema::create('admin_notifications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
                $table->string('type', 50);
                $table->string('title');
                $table->text('message')->nullable();
                $table->string('link')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['admin_id', 'read_at']);
            });
        }

        DB::table('tasks')->whereNotNull('completed_at')->update(['status' => 'completed']);
        DB::table('tasks')->whereNull('completed_at')->where('task_type', 'escalation')->update(['status' => 'escalated', 'is_escalated' => true]);
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_notifications');
        Schema::dropIfExists('task_activities');
        Schema::dropIfExists('task_attachments');
        Schema::dropIfExists('task_notes');

        Schema::table('tasks', function (Blueprint $table) {
            $columns = [
                'status', 'follow_up_date', 'is_escalated', 'assigned_by_admin_id',
                'escalated_at', 'escalated_by_admin_id', 'payer_id', 'automation_key',
            ];
            foreach ($columns as $column) {
                if (Schema::hasColumn('tasks', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
