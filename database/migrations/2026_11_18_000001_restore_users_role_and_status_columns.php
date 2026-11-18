<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Some databases (incl. the client DB) are missing users.role / users.status even though
 * the application writes both (bulk import, portal account creation, deactivation).
 * Only adds what is missing, so it is safe on every environment.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('role')->nullable()->after('phone');
            });
        }

        if (! Schema::hasColumn('users', 'status')) {
            Schema::table('users', function (Blueprint $table) {
                $table->tinyInteger('status')->default(1)->after('role');
            });
        }
    }

    public function down(): void
    {
        // Intentionally left blank: these columns belong to the base users schema.
    }
};
