<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'status')) {
                $table->tinyInteger('status')->default(1)->after('role');
            }
        });

        Schema::table('admins', function (Blueprint $table) {
            if (! Schema::hasColumn('admins', 'two_factor_code')) {
                $table->string('two_factor_code', 20)->nullable()->after('is_two_factor');
            }
            if (! Schema::hasColumn('admins', 'two_factor_expires_at')) {
                $table->timestamp('two_factor_expires_at')->nullable()->after('two_factor_code');
            }
            if (! Schema::hasColumn('admins', 'failed_login_attempts')) {
                $table->unsignedSmallInteger('failed_login_attempts')->default(0)->after('two_factor_expires_at');
            }
            if (! Schema::hasColumn('admins', 'locked_until')) {
                $table->timestamp('locked_until')->nullable()->after('failed_login_attempts');
            }
            if (! Schema::hasColumn('admins', 'password_changed_at')) {
                $table->timestamp('password_changed_at')->nullable()->after('password');
            }
        });

        Schema::table('documents', function (Blueprint $table) {
            if (! Schema::hasColumn('documents', 'visibility')) {
                $table->string('visibility', 30)->default('standard')->after('status');
            }
            if (! Schema::hasColumn('documents', 'allowed_roles')) {
                $table->json('allowed_roles')->nullable()->after('visibility');
            }
        });

        Schema::table('document_versions', function (Blueprint $table) {
            if (! Schema::hasColumn('document_versions', 'is_encrypted')) {
                $table->boolean('is_encrypted')->default(false)->after('is_current');
            }
        });

        try {
            Schema::table('sla_rules_admin', function (Blueprint $table) {
                $table->dropUnique(['rule_key']);
            });
        } catch (Throwable) {
            // Unique index may already be dropped
        }

        try {
            Schema::table('sla_rules_admin', function (Blueprint $table) {
                $table->index('rule_key');
            });
        } catch (Throwable) {
            // Index may already exist
        }

        Schema::table('sla_rules_admin', function (Blueprint $table) {
            if (! Schema::hasColumn('sla_rules_admin', 'payer_id')) {
                $table->foreignId('payer_id')->nullable()->after('rule_key')->constrained('payers')->nullOnDelete();
            }
            if (! Schema::hasColumn('sla_rules_admin', 'practice_id')) {
                $table->foreignId('practice_id')->nullable()->after('payer_id')->constrained('practices')->nullOnDelete();
            }
            if (! Schema::hasColumn('sla_rules_admin', 'case_type_id')) {
                $table->foreignId('case_type_id')->nullable()->after('practice_id')->constrained('case_types')->nullOnDelete();
            }
        });

        Schema::table('credentialing_cases', function (Blueprint $table) {
            if (! Schema::hasColumn('credentialing_cases', 'automation_paused')) {
                $table->boolean('automation_paused')->default(false)->after('do_not_automate');
            }
            if (! Schema::hasColumn('credentialing_cases', 'automation_pause_reason')) {
                $table->string('automation_pause_reason', 500)->nullable()->after('automation_paused');
            }
        });

        Schema::table('provider_details', function (Blueprint $table) {
            if (! Schema::hasColumn('provider_details', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        DB::statement('DROP VIEW IF EXISTS vw_credentialing_case_summary');
        DB::statement('
            CREATE VIEW vw_credentialing_case_summary AS
            SELECT
                c.id AS case_id,
                c.case_number,
                c.practice_id,
                pr.legal_name AS practice_name,
                c.provider_id,
                u.name AS provider_name,
                pd.npi,
                c.payer_id,
                pa.name AS payer_name,
                c.state,
                c.status_id,
                s.name AS status_name,
                s.dashboard_category,
                c.delay_owner_id,
                d_o.name AS delay_owner_name,
                c.assigned_admin_id,
                a.name AS assigned_admin_name,
                c.ready_to_bill,
                c.is_escalated,
                c.automation_paused,
                c.intake_date,
                c.submission_date,
                c.approval_date,
                c.effective_date,
                c.next_follow_up_date,
                c.last_action_at
            FROM credentialing_cases c
            LEFT JOIN practices pr ON pr.id = c.practice_id
            LEFT JOIN provider_details pd ON pd.id = c.provider_id
            LEFT JOIN users u ON u.id = pd.user_id
            LEFT JOIN payers pa ON pa.id = c.payer_id
            LEFT JOIN statuses s ON s.id = c.status_id
            LEFT JOIN delay_owners d_o ON d_o.id = c.delay_owner_id
            LEFT JOIN admins a ON a.id = c.assigned_admin_id
            WHERE c.deleted_at IS NULL
        ');

        DB::statement('DROP VIEW IF EXISTS vw_delay_ownership_summary');
        DB::statement("
            CREATE VIEW vw_delay_ownership_summary AS
            SELECT
                c.delay_owner_id,
                COALESCE(d_o.name, 'Unassigned') AS delay_owner_name,
                c.practice_id,
                c.payer_id,
                c.provider_id,
                c.assigned_admin_id,
                COUNT(c.id) AS active_case_count
            FROM credentialing_cases c
            JOIN statuses s ON s.id = c.status_id
            LEFT JOIN delay_owners d_o ON d_o.id = c.delay_owner_id
            WHERE c.deleted_at IS NULL
              AND s.dashboard_category NOT IN ('approved', 'closed')
            GROUP BY
                c.delay_owner_id,
                d_o.name,
                c.practice_id,
                c.payer_id,
                c.provider_id,
                c.assigned_admin_id
        ");

        DB::statement('DROP VIEW IF EXISTS vw_provider_compliance_summary');
        DB::statement('
            CREATE VIEW vw_provider_compliance_summary AS
            SELECT
                pd.id AS provider_id,
                u.name AS provider_name,
                pd.npi,
                pd.state,
                pd.status AS provider_status,
                (SELECT COUNT(*) FROM credentialing_cases cc WHERE cc.provider_id = pd.id AND cc.deleted_at IS NULL) AS total_cases,
                (SELECT COUNT(*) FROM documents d WHERE d.provider_id = pd.id AND d.deleted_at IS NULL) AS total_documents
            FROM provider_details pd
            LEFT JOIN users u ON u.id = pd.user_id
            WHERE pd.deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS vw_provider_compliance_summary');
        DB::statement('DROP VIEW IF EXISTS vw_delay_ownership_summary');
        DB::statement('DROP VIEW IF EXISTS vw_credentialing_case_summary');

        Schema::table('credentialing_cases', function (Blueprint $table) {
            $cols = array_filter(
                ['automation_paused', 'automation_pause_reason'],
                fn (string $col) => Schema::hasColumn('credentialing_cases', $col)
            );
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });

        Schema::table('sla_rules_admin', function (Blueprint $table) {
            if (Schema::hasColumn('sla_rules_admin', 'payer_id')) {
                $table->dropConstrainedForeignId('payer_id');
            }
            if (Schema::hasColumn('sla_rules_admin', 'practice_id')) {
                $table->dropConstrainedForeignId('practice_id');
            }
            if (Schema::hasColumn('sla_rules_admin', 'case_type_id')) {
                $table->dropConstrainedForeignId('case_type_id');
            }
        });

        Schema::table('document_versions', function (Blueprint $table) {
            if (Schema::hasColumn('document_versions', 'is_encrypted')) {
                $table->dropColumn('is_encrypted');
            }
        });

        Schema::table('documents', function (Blueprint $table) {
            $cols = array_filter(
                ['visibility', 'allowed_roles'],
                fn (string $col) => Schema::hasColumn('documents', $col)
            );
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });

        Schema::table('admins', function (Blueprint $table) {
            $cols = array_filter(
                ['two_factor_code', 'two_factor_expires_at', 'failed_login_attempts', 'locked_until', 'password_changed_at'],
                fn (string $col) => Schema::hasColumn('admins', $col)
            );
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
