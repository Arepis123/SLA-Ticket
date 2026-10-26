<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ticket_entry_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // comment, activity or work_log, with that entry's id
            $table->string('entry_type', 20);
            $table->unsignedBigInteger('entry_id');
            // Ticket status at the moment the entry was first seen
            $table->string('ticket_status', 30);
            $table->timestamp('seen_at');

            // Only the first sighting is kept, so the table grows with entries x viewers, not with visits.
            $table->unique(['user_id', 'entry_type', 'entry_id']);
            $table->index(['ticket_id', 'entry_type', 'entry_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_entry_views');
    }
};
