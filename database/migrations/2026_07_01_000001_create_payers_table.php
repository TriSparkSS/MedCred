<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('states_applicable')->nullable();
            $table->string('application_type')->nullable();
            $table->string('submission_channel')->nullable();
            $table->string('portal_url')->nullable();
            $table->text('portal_notes')->nullable();
            $table->string('fax')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->unsignedSmallInteger('turnaround_days')->nullable();
            $table->text('participation_rules')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payers');
    }
};
