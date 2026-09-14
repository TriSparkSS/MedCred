<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_details', function (Blueprint $table) {
            $table->decimal('malpractice_coverage_each_occurrence', 12, 2)->nullable()->after('malpractice_expiry');
            $table->decimal('malpractice_coverage_aggregate', 12, 2)->nullable()->after('malpractice_coverage_each_occurrence');
            $table->date('malpractice_effective_date')->nullable()->after('malpractice_coverage_aggregate');
        });
    }

    public function down(): void
    {
        Schema::table('provider_details', function (Blueprint $table) {
            $table->dropColumn([
                'malpractice_coverage_each_occurrence',
                'malpractice_coverage_aggregate',
                'malpractice_effective_date',
            ]);
        });
    }
};
