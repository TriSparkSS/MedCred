<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rebrand stored data from "Revantage" to "MedCred":
 * - delay owner "Revantage Team" -> "MedCred Team"
 * - notification template signatures "Revantage Credentialing" -> "MedCred Credentialing"
 * - saved mail "From name" if it still holds the old default
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->rebrand('Revantage', 'MedCred');
    }

    public function down(): void
    {
        $this->rebrand('MedCred', 'Revantage');
    }

    private function rebrand(string $from, string $to): void
    {
        if (Schema::hasTable('delay_owners')
            && ! DB::table('delay_owners')->where('name', "{$to} Team")->exists()) {
            DB::table('delay_owners')->where('name', "{$from} Team")->update(['name' => "{$to} Team"]);
        }

        if (Schema::hasTable('notification_templates')) {
            foreach (['body', 'subject'] as $column) {
                if (! Schema::hasColumn('notification_templates', $column)) {
                    continue;
                }

                DB::table('notification_templates')
                    ->where($column, 'like', "%{$from} Credentialing%")
                    ->update([$column => DB::raw("REPLACE({$column}, '{$from} Credentialing', '{$to} Credentialing')")]);
            }
        }

        if (Schema::hasTable('settings')) {
            $current = Setting::getDecrypted('mail.from_name');
            if ($current === "{$from} Credentialing") {
                Setting::set('mail.from_name', "{$to} Credentialing");
            }
        }
    }
};
