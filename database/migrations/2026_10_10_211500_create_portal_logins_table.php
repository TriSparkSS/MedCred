<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_logins', function (Blueprint $table) {
            $table->id();
            $table->morphs('loginable');
            $table->string('portal_name');
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->string('mfa_email')->nullable();
            $table->string('mfa_phone')->nullable();
            $table->text('security_notes')->nullable();
            $table->text('account_details')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_logins');
    }
};
