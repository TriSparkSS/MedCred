<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('practices', function (Blueprint $table) {
            $table->string('client_code', 3)->nullable()->unique()->after('legal_name');
        });
    }

    public function down(): void
    {
        Schema::table('practices', function (Blueprint $table) {
            $table->dropUnique(['client_code']);
            $table->dropColumn('client_code');
        });
    }
};
