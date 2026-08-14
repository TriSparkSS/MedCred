<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('uploaded_by_user_id')->nullable()->after('uploaded_by_admin_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('document_versions', function (Blueprint $table) {
            $table->foreignId('uploaded_by_user_id')->nullable()->after('uploaded_by_admin_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('case_activities', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('admin_id')->constrained('users')->nullOnDelete();
        });

        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('filename');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('success_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->json('errors')->nullable();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
        Schema::table('case_activities', fn (Blueprint $table) => $table->dropConstrainedForeignId('user_id'));
        Schema::table('document_versions', fn (Blueprint $table) => $table->dropConstrainedForeignId('uploaded_by_user_id'));
        Schema::table('documents', fn (Blueprint $table) => $table->dropConstrainedForeignId('uploaded_by_user_id'));
    }
};
