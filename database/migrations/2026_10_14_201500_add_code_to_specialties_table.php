<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('specialties', 'code')) {
            return;
        }

        Schema::table('specialties', function (Blueprint $table) {
            $table->string('code')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('specialties', 'code')) {
            return;
        }

        Schema::table('specialties', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }
};
