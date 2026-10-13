<?php

use App\Enums\TicketPriority;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sla_policies', function (Blueprint $table) {
            $table->id();
            $table->string('priority', 20)->unique();
            $table->unsignedInteger('response_minutes');
            $table->unsignedInteger('resolution_minutes');
            $table->timestamps();
        });

        DB::table('sla_policies')->insert(array_map(fn (TicketPriority $priority) => [
            'priority' => $priority->value,
            'response_minutes' => $priority->defaultTargets()['response'],
            'resolution_minutes' => $priority->defaultTargets()['resolution'],
            'created_at' => now(),
            'updated_at' => now(),
        ], TicketPriority::cases()));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sla_policies');
    }
};
