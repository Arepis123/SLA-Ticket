<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // One opening window per ISO weekday (1 = Monday ... 7 = Sunday). A day with no row is closed.
        Schema::create('business_hours', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('weekday')->unique();
            $table->time('opens_at');
            $table->time('closes_at');
            $table->timestamps();
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('name');
            $table->timestamps();
        });

        // A sensible office week to start from. It has no effect until a priority opts in below.
        DB::table('business_hours')->insert(array_map(fn (int $weekday) => [
            'weekday' => $weekday,
            'opens_at' => '09:00:00',
            'closes_at' => '18:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ], range(1, 5)));

        // Off by default so existing targets keep running 24/7 until an admin opts in.
        Schema::table('sla_policies', function (Blueprint $table) {
            $table->boolean('business_hours')->default(false)->after('resolution_minutes');
        });

        // The same pauses measured in business time, so a ticket can move between a 24/7 priority
        // and a business-hours one without its paused time being counted on the wrong clock.
        Schema::table('tickets', function (Blueprint $table) {
            $table->unsignedInteger('paused_business_minutes')->default(0)->after('paused_minutes');
        });

        DB::table('tickets')->update(['paused_business_minutes' => DB::raw('paused_minutes')]);

        Cache::forget('sla-policies');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('paused_business_minutes');
        });

        Schema::table('sla_policies', function (Blueprint $table) {
            $table->dropColumn('business_hours');
        });

        Schema::dropIfExists('holidays');
        Schema::dropIfExists('business_hours');

        Cache::forget('sla-policies');
        Cache::forget('business-calendar');
    }
};
