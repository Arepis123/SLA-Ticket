<?php

namespace App\Services\Ai;

/**
 * What the model said in one turn: its text, and any tools it wants run.
 */
final readonly class ModelTurn
{
    /**
     * @param  list<array{id: string, name: string, input: array<string, mixed>}>  $toolCalls
     * @param  mixed  $assistantContent  The turn exactly as returned, to send back as the assistant message.
     */
    public function __construct(
        public string $text,
        public array $toolCalls,
        public mixed $assistantContent,
    ) {}

    public function wantsTools(): bool
    {
        return $this->toolCalls !== [];
    }
}
