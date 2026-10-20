<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_attachments', function (Blueprint $table) {
            $table->foreignId('ticket_work_log_id')->nullable()->after('ticket_comment_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_attachments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ticket_work_log_id');
        });
    }
};
