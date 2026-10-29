<?php

namespace App\Services\Ai;

use Anthropic\Beta\Messages\BetaMessage;
use Anthropic\Beta\Messages\BetaTextBlock;
use Anthropic\Beta\Messages\BetaToolUseBlock;
use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\PermissionDeniedException;
use Anthropic\Core\Exceptions\RateLimitException;
use Closure;
use Illuminate\Support\Facades\Log;

/**
 * Claude through the official SDK. Effort is kept low: these are short,
 * read-only answers, and the account pays per token.
 */
class ClaudeLanguageModel implements LanguageModel
{
    protected ?Client $client = null;

    public function __construct(
        protected ?string $apiKey,
        protected string $model,
    ) {}

    public function json(string $system, string $prompt, array $schema, int $maxTokens): array
    {
        $message = $this->send(fn (Client $client) => $client->beta->messages->create(
            maxTokens: $maxTokens,
            messages: [['role' => 'user', 'content' => $prompt]],
            model: $this->model,
            system: $system,
            outputConfig: [
                'effort' => 'low',
                'format' => ['type' => 'json_schema', 'schema' => $schema],
            ],
            fallbacks: 'default',
            betas: ['server-side-fallback-2026-07-01'],
        ));

        $decoded = json_decode($this->text($message), true);

        if (! is_array($decoded)) {
            throw new AiUnavailable(__('Claude sent an answer the app could not read. Please try again.'));
        }

        return $decoded;
    }

    public function turn(string $system, array $messages, array $tools, int $maxTokens, bool $allowTools = true): ModelTurn
    {
        $message = $this->send(fn (Client $client) => $client->beta->messages->create(
            maxTokens: $maxTokens,
            messages: $messages,
            model: $this->model,
            // Caches the conversation so far, so each tool round only pays full price for what is new.
            cacheControl: ['type' => 'ephemeral'],
            system: $system,
            outputConfig: ['effort' => 'low'],
            toolChoice: $allowTools ? ['type' => 'auto'] : ['type' => 'none'],
            tools: $tools,
            fallbacks: 'default',
            betas: ['server-side-fallback-2026-07-01'],
        ));

        $toolCalls = [];
        foreach ($message->content as $block) {
            if ($block instanceof BetaToolUseBlock) {
                $toolCalls[] = ['id' => $block->id, 'name' => $block->name, 'input' => $block->input];
            }
        }

        return new ModelTurn(
            text: $toolCalls === [] ? $this->text($message) : $this->text($message, required: false),
            toolCalls: $toolCalls,
            assistantContent: $message->content,
        );
    }

    /**
     * @param  Closure(Client): BetaMessage  $request
     */
    protected function send(Closure $request): BetaMessage
    {
        if (blank($this->apiKey)) {
            throw new AiUnavailable(__('AI features are not set up on the server yet.'));
        }

        try {
            $message = $request($this->client ??= new Client(apiKey: $this->apiKey));
        } catch (APIException $e) {
            Log::warning('Claude request failed', ['exception' => $e::class, 'message' => $e->getMessage()]);

            throw new AiUnavailable($this->explain($e), previous: $e);
        }

        // Tokens are what the account is billed for, so every call is logged.
        Log::info('Claude usage', [
            'model' => $message->model,
            'input' => $message->usage->inputTokens,
            'output' => $message->usage->outputTokens,
            'cache_read' => $message->usage->cacheReadInputTokens,
            'cache_write' => $message->usage->cacheCreationInputTokens,
        ]);

        if ($message->stopReason === 'refusal') {
            throw new AiUnavailable(__('Claude declined to answer that one.'));
        }

        return $message;
    }

    protected function text(BetaMessage $message, bool $required = true): string
    {
        $text = '';
        foreach ($message->content as $block) {
            if ($block instanceof BetaTextBlock) {
                $text .= $block->text;
            }
        }

        if ($required && trim($text) === '') {
            throw new AiUnavailable(__('Claude did not finish its answer. Please try again.'));
        }

        return trim($text);
    }

    protected function explain(APIException $e): string
    {
        return match (true) {
            $e instanceof AuthenticationException, $e instanceof PermissionDeniedException => __('The server\'s Claude API key was rejected.'),
            $e instanceof RateLimitException => __('Claude is busy right now. Please try again in a minute.'),
            $e instanceof APIStatusException && str_contains(strtolower($e->getMessage()), 'credit balance') => __('The Claude account is out of credit.'),
            $e instanceof APIConnectionException => __('The server could not reach Claude. Please try again.'),
            default => __('Claude could not answer right now. Please try again.'),
        };
    }
}
