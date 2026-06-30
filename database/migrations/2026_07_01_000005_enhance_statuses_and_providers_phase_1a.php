<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('statuses', function (Blueprint $table) {
            $table->unsignedSmallInteger('sort_order')->default(0)->after('is_active');
            $table->string('dashboard_category')->nullable()->after('sort_order');
            $table->foreignId('delay_owner_id')->nullable()->after('dashboard_category')->constrained('delay_owners')->nullOnDelete();
        });

        Schema::table('provider_details', function (Blueprint $table) {
            $table->string('taxonomy_code')->nullable()->after('dea');
            $table->string('pecos_id')->nullable()->after('taxonomy_code');
            $table->boolean('pecos_enrolled')->default(false)->after('pecos_id');
            $table->string('malpractice_carrier')->nullable()->after('pecos_enrolled');
            $table->string('malpractice_policy_number')->nullable()->after('malpractice_carrier');
            $table->date('malpractice_expiry')->nullable()->after('malpractice_policy_number');
            $table->string('board_certification')->nullable()->after('malpractice_expiry');
            $table->date('board_cert_expiry')->nullable()->after('board_certification');
            $table->string('cds_number')->nullable()->after('board_cert_expiry');
            $table->string('cds_state')->nullable()->after('cds_number');
            $table->text('work_history')->nullable()->after('cds_state');
            $table->json('licensed_states')->nullable()->after('work_history');
        });
    }

    public function down(): void
    {
        Schema::table('statuses', function (Blueprint $table) {
            $table->dropForeign(['delay_owner_id']);
            $table->dropColumn(['sort_order', 'dashboard_category', 'delay_owner_id']);
        });

        Schema::table('provider_details', function (Blueprint $table) {
            $table->dropColumn([
                'taxonomy_code',
                'pecos_id',
                'pecos_enrolled',
                'malpractice_carrier',
                'malpractice_policy_number',
                'malpractice_expiry',
                'board_certification',
                'board_cert_expiry',
                'cds_number',
                'cds_state',
                'work_history',
                'licensed_states',
            ]);
        });
    }
};
