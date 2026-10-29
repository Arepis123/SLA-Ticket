<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\RateLimiter;

/**
 * A server-wide cap on AI uses per day, so a bug or a burst of taps cannot run
 * up the Claude bill. Only real calls count; cached summaries are free.
 */
class AiBudget
{
    public function __construct(protected int $dailyLimit) {}

    /**
     * @throws AiUnavailable
     */
    public function spend(): void
    {
        $key = 'ai-daily:'.now()->toDateString();

        if (RateLimiter::tooManyAttempts($key, $this->dailyLimit)) {
            throw new AiUnavailable(__('Today\'s AI limit has been reached. It resets at midnight.'));
        }

        RateLimiter::hit($key, 86400);
    }
}
