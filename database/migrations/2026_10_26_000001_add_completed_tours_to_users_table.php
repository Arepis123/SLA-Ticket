<?php

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
        Schema::table('users', function (Blueprint $table) {
            $table->json('completed_tours')->nullable()->after('is_active');
        });

        // The guided tours are for new clients; people already using the system skip them.
        $now = now()->toIso8601String();

        DB::table('users')->update(['completed_tours' => json_encode([
            'dashboard' => $now,
            'tickets' => $now,
            'report-bug' => $now,
        ])]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('completed_tours');
        });
    }
};
