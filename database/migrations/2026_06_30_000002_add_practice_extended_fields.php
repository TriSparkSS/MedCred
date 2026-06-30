<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('practices', function (Blueprint $table) {
            $table->string('license_number')->nullable()->after('status');
            $table->string('bank_name')->nullable()->after('license_number');
            $table->string('bank_account')->nullable()->after('bank_name');
            $table->string('bank_routing_number')->nullable()->after('bank_account');
            $table->text('bank_address')->nullable()->after('bank_routing_number');
            $table->string('bank_phone')->nullable()->after('bank_address');
            $table->string('document_path')->nullable()->after('bank_phone');
            $table->string('document_original_name')->nullable()->after('document_path');
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->string('county')->nullable()->after('zip_code');
            $table->string('type')->default('primary')->after('addressable_id');
        });
    }

    public function down(): void
    {
        Schema::table('practices', function (Blueprint $table) {
            $table->dropColumn([
                'license_number',
                'bank_name',
                'bank_account',
                'bank_routing_number',
                'bank_address',
                'bank_phone',
                'document_path',
                'document_original_name',
            ]);
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn(['county', 'type']);
        });
    }
};
