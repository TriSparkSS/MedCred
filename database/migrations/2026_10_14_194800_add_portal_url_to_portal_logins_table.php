<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('portal_logins', 'portal_url')) {
            return;
        }

        Schema::table('portal_logins', function (Blueprint $table) {
            $table->string('portal_url')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('portal_logins', 'portal_url')) {
            return;
        }

        Schema::table('portal_logins', function (Blueprint $table) {
            $table->dropColumn('portal_url');
        });
    }
};
