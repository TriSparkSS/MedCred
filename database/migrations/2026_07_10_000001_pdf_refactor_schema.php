<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('provider_practice_locations')) {
            Schema::create('provider_practice_locations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('provider_id')->constrained('provider_details')->cascadeOnDelete();
                $table->foreignId('practice_id')->constrained()->cascadeOnDelete();
                $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->string('role')->nullable();
                $table->boolean('is_primary')->default(false);
                $table->timestamps();

                $table->index(['provider_id', 'practice_id']);
            });
        }

        Schema::table('credentialing_cases', function (Blueprint $table) {
            if (! Schema::hasColumn('credentialing_cases', 'provider_practice_location_id')) {
                $table->foreignId('provider_practice_location_id')->nullable()->after('location_id')
                    ->constrained('provider_practice_locations')->nullOnDelete();
            }
            if (! Schema::hasColumn('credentialing_cases', 'assigned_manager_id')) {
                $table->foreignId('assigned_manager_id')->nullable()->after('assigned_admin_id')
                    ->constrained('admins')->nullOnDelete();
            }
            if (! Schema::hasColumn('credentialing_cases', 'acknowledgement_date')) {
                $table->date('acknowledgement_date')->nullable()->after('submission_date');
            }
            if (! Schema::hasColumn('credentialing_cases', 'approval_date')) {
                $table->date('approval_date')->nullable()->after('acknowledgement_date');
            }
            if (! Schema::hasColumn('credentialing_cases', 'payer_provider_id')) {
                $table->string('payer_provider_id')->nullable()->after('effective_date');
            }
            if (! Schema::hasColumn('credentialing_cases', 'payer_group_id')) {
                $table->string('payer_group_id')->nullable()->after('payer_provider_id');
            }
            if (! Schema::hasColumn('credentialing_cases', 'eft_status')) {
                $table->string('eft_status')->nullable()->after('payer_group_id');
            }
            if (! Schema::hasColumn('credentialing_cases', 'era_status')) {
                $table->string('era_status')->nullable()->after('eft_status');
            }
            if (! Schema::hasColumn('credentialing_cases', 'billing_notified')) {
                $table->boolean('billing_notified')->default(false)->after('era_status');
            }
            if (! Schema::hasColumn('credentialing_cases', 'billing_notes')) {
                $table->text('billing_notes')->nullable()->after('billing_notified');
            }
            if (! Schema::hasColumn('credentialing_cases', 'ready_to_bill')) {
                $table->boolean('ready_to_bill')->default(false)->after('billing_notes');
            }
            if (! Schema::hasColumn('credentialing_cases', 'do_not_automate')) {
                $table->boolean('do_not_automate')->default(false)->after('is_escalated');
            }
        });

        Schema::table('documents', function (Blueprint $table) {
            if (! Schema::hasColumn('documents', 'verification_status')) {
                $table->string('verification_status')->default('uploaded')->after('status');
            }
            if (! Schema::hasColumn('documents', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('verification_status');
            }
            if (! Schema::hasColumn('documents', 'verified_by_admin_id')) {
                $table->foreignId('verified_by_admin_id')->nullable()->after('rejection_reason')
                    ->constrained('admins')->nullOnDelete();
            }
            if (! Schema::hasColumn('documents', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('verified_by_admin_id');
            }
        });

        Schema::table('document_types', function (Blueprint $table) {
            if (! Schema::hasColumn('document_types', 'requires_expiry')) {
                $table->boolean('requires_expiry')->default(false)->after('is_active');
            }
            if (! Schema::hasColumn('document_types', 'expiry_warning_days')) {
                $table->unsignedInteger('expiry_warning_days')->nullable()->after('requires_expiry');
            }
            if (! Schema::hasColumn('document_types', 'accepted_mime_types')) {
                $table->json('accepted_mime_types')->nullable()->after('expiry_warning_days');
            }
        });

        Schema::table('locations', function (Blueprint $table) {
            if (! Schema::hasColumn('locations', 'address_type')) {
                $table->string('address_type')->default('service')->after('name');
            }
            if (! Schema::hasColumn('locations', 'pay_to_address')) {
                $table->string('pay_to_address')->nullable()->after('zip_code');
            }
            if (! Schema::hasColumn('locations', 'mailing_address')) {
                $table->string('mailing_address')->nullable()->after('pay_to_address');
            }
        });

        if (! Schema::hasTable('delay_owner_histories')) {
            Schema::create('delay_owner_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('credentialing_case_id')->constrained()->cascadeOnDelete();
                $table->foreignId('delay_owner_id')->nullable()->constrained('delay_owners')->nullOnDelete();
                $table->foreignId('previous_delay_owner_id')->nullable()->constrained('delay_owners')->nullOnDelete();
                $table->string('source')->default('rule');
                $table->text('reason')->nullable();
                $table->foreignId('changed_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('business_calendars')) {
            Schema::create('business_calendars', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->boolean('exclude_weekends')->default(true);
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('holidays')) {
            Schema::create('holidays', function (Blueprint $table) {
                $table->id();
                $table->foreignId('business_calendar_id')->constrained()->cascadeOnDelete();
                $table->date('date');
                $table->string('name')->nullable();
                $table->timestamps();

                $table->unique(['business_calendar_id', 'date']);
            });
        }

        if (! Schema::hasTable('form_templates')) {
            Schema::create('form_templates', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('template_key')->unique();
                $table->string('version')->default('1.0');
                $table->text('description')->nullable();
                $table->string('file_path')->nullable();
                $table->json('field_mapping')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('packet_generations')) {
            Schema::create('packet_generations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('credentialing_case_id')->constrained()->cascadeOnDelete();
                $table->foreignId('form_template_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('generated_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
                $table->string('file_path');
                $table->string('form_version')->nullable();
                $table->json('included_document_ids')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('notification_rules')) {
            Schema::create('notification_rules', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('event_key');
                $table->string('channel')->default('database');
                $table->json('recipient_roles')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sla_rules_admin')) {
            Schema::create('sla_rules_admin', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('rule_key')->unique();
                $table->string('dashboard_category')->nullable();
                $table->unsignedInteger('business_days')->default(3);
                $table->string('action')->default('reminder');
                $table->foreignId('notification_template_id')->nullable()->constrained()->nullOnDelete();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('delay_rules')) {
            Schema::create('delay_rules', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->foreignId('status_id')->nullable()->constrained('statuses')->nullOnDelete();
                $table->string('event_key')->nullable();
                $table->foreignId('delay_owner_id')->constrained('delay_owners');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('payer_contacts')) {
            Schema::create('payer_contacts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payer_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->string('contact_type')->default('general');
                $table->boolean('is_primary')->default(false);
                $table->timestamps();
            });
        }

        Schema::table('email_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('email_messages', 'provider_id')) {
                $table->foreignId('provider_id')->nullable()->after('credentialing_case_id')
                    ->constrained('provider_details')->nullOnDelete();
            }
            if (! Schema::hasColumn('email_messages', 'queue_category')) {
                $table->string('queue_category')->nullable()->after('status');
            }
            if (! Schema::hasColumn('email_messages', 'is_unlinked')) {
                $table->boolean('is_unlinked')->default(false)->after('queue_category');
            }
            if (! Schema::hasColumn('email_messages', 'has_pending_attachments')) {
                $table->boolean('has_pending_attachments')->default(false)->after('is_unlinked');
            }
        });
    }

    public function down(): void
    {
        Schema::table('email_messages', function (Blueprint $table) {
            if (Schema::hasColumn('email_messages', 'provider_id')) {
                $table->dropConstrainedForeignId('provider_id');
            }
            $cols = array_filter(['queue_category', 'is_unlinked', 'has_pending_attachments'], fn ($c) => Schema::hasColumn('email_messages', $c));
            if ($cols) {
                $table->dropColumn($cols);
            }
        });

        Schema::dropIfExists('payer_contacts');
        Schema::dropIfExists('delay_rules');
        Schema::dropIfExists('sla_rules_admin');
        Schema::dropIfExists('notification_rules');
        Schema::dropIfExists('packet_generations');
        Schema::dropIfExists('form_templates');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('business_calendars');
        Schema::dropIfExists('delay_owner_histories');

        Schema::table('locations', function (Blueprint $table) {
            $cols = array_filter(['address_type', 'pay_to_address', 'mailing_address'], fn ($c) => Schema::hasColumn('locations', $c));
            if ($cols) {
                $table->dropColumn($cols);
            }
        });

        Schema::table('document_types', function (Blueprint $table) {
            $cols = array_filter(['requires_expiry', 'expiry_warning_days', 'accepted_mime_types'], fn ($c) => Schema::hasColumn('document_types', $c));
            if ($cols) {
                $table->dropColumn($cols);
            }
        });

        Schema::table('documents', function (Blueprint $table) {
            if (Schema::hasColumn('documents', 'verified_by_admin_id')) {
                $table->dropConstrainedForeignId('verified_by_admin_id');
            }
            $cols = array_filter(['verification_status', 'rejection_reason', 'verified_at'], fn ($c) => Schema::hasColumn('documents', $c));
            if ($cols) {
                $table->dropColumn($cols);
            }
        });

        Schema::table('credentialing_cases', function (Blueprint $table) {
            if (Schema::hasColumn('credentialing_cases', 'provider_practice_location_id')) {
                $table->dropConstrainedForeignId('provider_practice_location_id');
            }
            if (Schema::hasColumn('credentialing_cases', 'assigned_manager_id')) {
                $table->dropConstrainedForeignId('assigned_manager_id');
            }
            $cols = array_filter([
                'acknowledgement_date', 'approval_date', 'payer_provider_id', 'payer_group_id',
                'eft_status', 'era_status', 'billing_notified', 'billing_notes', 'ready_to_bill', 'do_not_automate',
            ], fn ($c) => Schema::hasColumn('credentialing_cases', $c));
            if ($cols) {
                $table->dropColumn($cols);
            }
        });

        Schema::dropIfExists('provider_practice_locations');
    }
};
