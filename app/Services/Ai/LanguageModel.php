<?php

namespace App\Services\Ai;

use Anthropic\Beta\Messages\BetaMessageParam;
use Anthropic\Beta\Messages\BetaTool;

/**
 * The few things the AI features ask of a language model. Claude implements
 * it in production; tests swap in a scripted fake so they never call the API.
 *
 * Messages and tools use the Anthropic SDK's array shapes.
 *
 * @phpstan-import-type BetaMessageParamShape from BetaMessageParam
 * @phpstan-import-type BetaToolShape from BetaTool
 */
interface LanguageModel
{
    /**
     * One reply constrained to a JSON schema, decoded.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     *
     * @throws AiUnavailable
     */
    public function json(string $system, string $prompt, array $schema, int $maxTokens): array;

    /**
     * One turn of a conversation that may use tools. With $allowTools false
     * the model must answer in text, which ends a tool loop.
     *
     * @param  list<BetaMessageParamShape>  $messages
     * @param  list<BetaToolShape>  $tools
     *
     * @throws AiUnavailable
     */
    public function turn(string $system, array $messages, array $tools, int $maxTokens, bool $allowTools = true): ModelTurn;
}
