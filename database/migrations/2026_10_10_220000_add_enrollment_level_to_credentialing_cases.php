<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credentialing_cases', function (Blueprint $table) {
            $table->string('enrollment_level', 20)->default('individual')->after('case_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('credentialing_cases', function (Blueprint $table) {
            $table->dropColumn('enrollment_level');
        });
    }
};
