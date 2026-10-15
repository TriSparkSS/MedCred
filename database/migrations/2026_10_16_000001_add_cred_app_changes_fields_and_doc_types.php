<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_details', function (Blueprint $table) {
            if (! Schema::hasColumn('provider_details', 'dob')) {
                $table->date('dob')->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('provider_details', 'ssn')) {
                $table->text('ssn')->nullable()->after('dob');
            }
            if (! Schema::hasColumn('provider_details', 'degree')) {
                $table->string('degree', 100)->nullable()->after('specialty_id');
            }
            if (! Schema::hasColumn('provider_details', 'provider_type')) {
                $table->string('provider_type', 100)->nullable()->after('degree');
            }
            if (! Schema::hasColumn('provider_details', 'home_address')) {
                $table->string('home_address')->nullable()->after('zip');
            }
            if (! Schema::hasColumn('provider_details', 'home_city')) {
                $table->string('home_city', 100)->nullable()->after('home_address');
            }
            if (! Schema::hasColumn('provider_details', 'home_state')) {
                $table->string('home_state', 50)->nullable()->after('home_city');
            }
            if (! Schema::hasColumn('provider_details', 'home_zip')) {
                $table->string('home_zip', 20)->nullable()->after('home_state');
            }
        });

        Schema::table('portal_logins', function (Blueprint $table) {
            if (! Schema::hasColumn('portal_logins', 'assigned_id')) {
                $table->string('assigned_id', 50)->nullable()->after('portal_name');
            }
        });

        if (Schema::hasTable('document_types')) {
            $now = now();
            $newDocTypes = [
                ['name' => 'CDS', 'is_state_specific' => true],
                ['name' => 'CP-575 / IRS', 'is_state_specific' => false],
                ['name' => 'Education Certificate / Degree', 'is_state_specific' => false],
                ['name' => 'Payer Correspondence', 'is_state_specific' => false],
                ['name' => 'Additional Info Request Letter', 'is_state_specific' => false],
                ['name' => 'Submission Proof', 'is_state_specific' => false],
            ];

            foreach ($newDocTypes as $docType) {
                $exists = DB::table('document_types')->where('name', $docType['name'])->exists();
                if (! $exists) {
                    DB::table('document_types')->insert([
                        'name' => $docType['name'],
                        'is_active' => true,
                        'is_state_specific' => $docType['is_state_specific'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                } else {
                    DB::table('document_types')
                        ->where('name', $docType['name'])
                        ->update([
                            'is_active' => true,
                            'is_state_specific' => $docType['is_state_specific'],
                            'updated_at' => $now,
                        ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('provider_details', function (Blueprint $table) {
            $cols = array_filter(
                ['dob', 'ssn', 'degree', 'provider_type', 'home_address', 'home_city', 'home_state', 'home_zip'],
                fn (string $col) => Schema::hasColumn('provider_details', $col)
            );
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });

        Schema::table('portal_logins', function (Blueprint $table) {
            if (Schema::hasColumn('portal_logins', 'assigned_id')) {
                $table->dropColumn('assigned_id');
            }
        });
    }
};
