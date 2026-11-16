<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Until now the app ran in UTC, so every stored time is UTC wall-clock.
     * The app now runs in APP_TIMEZONE (Asia/Kuala_Lumpur), which reads those
     * same values as local time, so shift them forward by the zone's offset.
     */
    private const COLUMNS = [
        'users' => ['email_verified_at', 'two_factor_confirmed_at', 'created_at', 'updated_at'],
        'password_reset_tokens' => ['created_at'],
        'passkeys' => ['last_used_at', 'created_at', 'updated_at'],
        'companies' => ['created_at', 'updated_at'],
        'projects' => ['created_at', 'updated_at'],
        'sla_policies' => ['created_at', 'updated_at'],
        'tickets' => [
            'response_due_at', 'resolution_due_at', 'first_responded_at', 'resolved_at',
            'closed_at', 'paused_at', 'response_breached_at', 'resolution_breached_at',
            'at_risk_notified_at', 'client_reminded_at', 'created_at', 'updated_at',
        ],
        'ticket_comments' => ['created_at', 'updated_at'],
        'ticket_attachments' => ['created_at', 'updated_at'],
        'ticket_activities' => ['created_at', 'updated_at'],
        'ticket_work_logs' => ['created_at', 'updated_at'],
        'ticket_work_log_reads' => ['last_read_at', 'created_at', 'updated_at'],
        'ticket_entry_views' => ['seen_at'],
        'business_hours' => ['created_at', 'updated_at'],
        'holidays' => ['created_at', 'updated_at'],
        'saved_views' => ['created_at', 'updated_at'],
        'push_subscriptions' => ['created_at', 'updated_at'],
        'personal_access_tokens' => ['last_used_at', 'expires_at', 'created_at', 'updated_at'],
        'device_tokens' => ['created_at', 'updated_at'],
    ];

    public function up(): void
    {
        $this->shift(1);
    }

    public function down(): void
    {
        $this->shift(-1);
    }

    private function shift(int $direction): void
    {
        $minutes = CarbonImmutable::now(config('app.timezone'))->utcOffset() * $direction;

        if ($minutes === 0) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $updates = [];

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                $updates[$column] = DB::raw(match ($driver) {
                    'sqlite' => "datetime({$column}, '".sprintf('%+d', $minutes)." minutes')",
                    'pgsql' => "{$column} + interval '{$minutes} minutes'",
                    default => "DATE_ADD({$column}, INTERVAL {$minutes} MINUTE)",
                });
            }

            if ($updates !== []) {
                DB::table($table)->update($updates);
            }
        }
    }
};
