<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks;

use JesseGall\PhpTypes\Option;

/**
 * What ONE handler emitted for a hook moment. The harness protocol is an array at both ends — it is
 * what arrives on stdin and what goes back on stdout — but between those two points the payload
 * answers questions rather than being indexed: whether it blocks and why, what context it injected,
 * whether it asked to stay out of the transcript.
 */
final readonly class HookEmission
{
    /**
     * @param  array<string, mixed>  $payload
     */
    private function __construct(private array $payload) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function of(array $payload): self
    {
        return new self($payload);
    }

    /**
     * The reason this handler BLOCKS on — none when it does not block, and none when it blocks
     * without saying anything worth printing. A handler that emits a non-string reason has not
     * given one.
     *
     * @return Option<string>
     */
    public function blockReason(): Option
    {
        if (($this->payload['decision'] ?? null) !== 'block') {
            return Option::none();
        }

        $reason = $this->payload['reason'] ?? null;

        return is_string($reason) ? Option::fromTruthy(trim($reason)) : Option::none();
    }

    /**
     * The context this handler injected — none when it injected nothing.
     *
     * @return Option<string>
     */
    public function context(): Option
    {
        $context = $this->payload['hookSpecificOutput']['additionalContext'] ?? null;

        return is_string($context) ? Option::fromTruthy($context) : Option::none();
    }

    /**
     * Did this handler ask for its output to stay out of the transcript? Only when every handler
     * that spoke asks does the merged response suppress.
     */
    public function suppressesOutput(): bool
    {
        return ($this->payload['suppressOutput'] ?? false) === true;
    }
}
