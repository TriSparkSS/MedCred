<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_details', function (Blueprint $table) {
            $table->string('caqh_id')->nullable()->after('npi');
            $table->string('license_number')->nullable()->after('caqh_id');
            $table->string('license_state')->nullable()->after('license_number');
            $table->string('dea')->nullable()->after('license_state');
        });
    }

    public function down(): void
    {
        Schema::table('provider_details', function (Blueprint $table) {
            $table->dropColumn(['caqh_id', 'license_number', 'license_state', 'dea']);
        });
    }
};
