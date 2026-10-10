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
            if (! Schema::hasColumn('provider_details', 'status_remarks')) {
                $table->text('status_remarks')->nullable()->after('status');
            }
        });

        DB::table('provider_details')->where('status', 'approved')->update(['status' => 'active']);
        DB::table('provider_details')->whereIn('status', ['pending', 'rejected'])->update(['status' => 'inactive']);
    }

    public function down(): void
    {
        Schema::table('provider_details', function (Blueprint $table) {
            if (Schema::hasColumn('provider_details', 'status_remarks')) {
                $table->dropColumn('status_remarks');
            }
        });
    }
};
