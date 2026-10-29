<?php

namespace App\Services\Ai;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * The AI feature cannot answer right now: not configured, over the daily
 * limit, or Claude is unreachable or declined. The message is safe to show.
 */
class AiUnavailable extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 503);
    }
}
