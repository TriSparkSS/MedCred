<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('provider_details', function (Blueprint $table) {
                $table->dropForeign(['specialty_id']);
                $table->foreign('specialty_id')
                    ->references('id')
                    ->on('specialties')
                    ->nullOnDelete();
            });
        }

        Schema::table('provider_details', function (Blueprint $table) {
            $table->index('npi');
        });
    }

    public function down(): void
    {
        Schema::table('provider_details', function (Blueprint $table) {
            $table->dropIndex(['npi']);
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('provider_details', function (Blueprint $table) {
                $table->dropForeign(['specialty_id']);
                $table->foreign('specialty_id')
                    ->references('id')
                    ->on('specialties')
                    ->cascadeOnDelete();
            });
        }
    }
};
