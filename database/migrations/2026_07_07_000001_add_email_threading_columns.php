<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('email_messages', 'message_id')) {
                $table->string('message_id')->nullable()->unique()->after('external_message_id');
            }
            if (! Schema::hasColumn('email_messages', 'in_reply_to')) {
                $table->string('in_reply_to')->nullable()->after('message_id');
            }
            if (! Schema::hasColumn('email_messages', 'references')) {
                $table->text('references')->nullable()->after('in_reply_to');
            }
            if (! Schema::hasColumn('email_messages', 'imap_uid')) {
                $table->unsignedBigInteger('imap_uid')->nullable()->after('references');
            }
        });
    }

    public function down(): void
    {
        Schema::table('email_messages', function (Blueprint $table) {
            $cols = array_filter(
                ['message_id', 'in_reply_to', 'references', 'imap_uid'],
                fn ($c) => Schema::hasColumn('email_messages', $c)
            );
            if ($cols) {
                $table->dropColumn($cols);
            }
        });
    }
};
