<?php

namespace Tests\Support;

use App\Services\Ai\LanguageModel;
use App\Services\Ai\ModelTurn;
use PHPUnit\Framework\Assert;

/**
 * Plays back scripted replies instead of calling Claude, and records what it
 * was sent so tests can check the prompts and tool results.
 */
class FakeLanguageModel implements LanguageModel
{
    /** @var list<array<string, mixed>|ModelTurn> */
    protected array $replies;

    /** @var list<array<string, mixed>> */
    public array $calls = [];

    /**
     * @param  array<string, mixed>|ModelTurn  ...$replies
     */
    public function __construct(array|ModelTurn ...$replies)
    {
        $this->replies = array_values($replies);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function toolCall(string $name, array $input, string $id = 'call_1'): ModelTurn
    {
        return new ModelTurn('', [['id' => $id, 'name' => $name, 'input' => $input]], [['type' => 'tool_use', 'id' => $id, 'name' => $name, 'input' => $input]]);
    }

    public static function answer(string $text): ModelTurn
    {
        return new ModelTurn($text, [], [['type' => 'text', 'text' => $text]]);
    }

    public function json(string $system, string $prompt, array $schema, int $maxTokens): array
    {
        $this->calls[] = ['type' => 'json', 'prompt' => $prompt];

        $reply = array_shift($this->replies);
        Assert::assertIsArray($reply, 'The fake model ran out of JSON replies.');

        return $reply;
    }

    public function turn(string $system, array $messages, array $tools, int $maxTokens, bool $allowTools = true): ModelTurn
    {
        $this->calls[] = ['type' => 'turn', 'messages' => $messages, 'allowTools' => $allowTools];

        $reply = array_shift($this->replies);
        Assert::assertInstanceOf(ModelTurn::class, $reply, 'The fake model ran out of turns.');

        return $reply;
    }

    /**
     * The decoded result of the most recent tool call sent back to the model.
     *
     * @return array<string, mixed>
     */
    public function lastToolResult(): array
    {
        $last = end($this->calls);
        Assert::assertIsArray($last, 'The fake model was never called.');
        $messages = $last['messages'];

        return json_decode(end($messages)['content'][0]['content'], true);
    }
}
