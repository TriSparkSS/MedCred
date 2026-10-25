<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('document_types')) {
            return;
        }

        if (! DB::table('document_types')->where('name', 'Bank Document')->exists()) {
            DB::table('document_types')->insert([
                'name' => 'Bank Document',
                'is_active' => true,
                'is_state_specific' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Intentionally left in place: documents may already reference this type.
    }
};
