<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('client_reminded_at')->nullable()->after('at_risk_notified_at');
            $table->string('client_reminded_status')->nullable()->after('client_reminded_at');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['client_reminded_at', 'client_reminded_status']);
        });
    }
};
