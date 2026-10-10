<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('document_number')->nullable()->after('title');
        });

        Schema::table('practices', function (Blueprint $table) {
            $table->string('license_name')->nullable()->after('license_number');
            $table->date('license_effective_date')->nullable()->after('license_name');
            $table->date('license_expiry_date')->nullable()->after('license_effective_date');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('document_number');
        });

        Schema::table('practices', function (Blueprint $table) {
            $table->dropColumn(['license_name', 'license_effective_date', 'license_expiry_date']);
        });
    }
};
