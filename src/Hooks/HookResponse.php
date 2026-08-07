<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks;

use JesseGall\PhpTypes\Option;

/**
 * What the harness is told after a hook moment — three shapes and no more: SILENT, a BLOCK carrying a
 * reason, or injected CONTEXT (kept out of the transcript when the handler asked). A block wins over
 * context, and it is the ONE place the wire shape is written ({@see json}), so a handler names what it
 * wants to say and never the protocol's own keys.
 */
final readonly class HookResponse
{
    /**
     * @param  Option<string>  $blockReason
     * @param  Option<string>  $context
     */
    private function __construct(
        public Option $blockReason,
        public Option $context,
        public bool $suppressOutput = false,
    ) {}

    /**
     * Nothing to say.
     */
    public static function silent(): self
    {
        return new self(Option::none(), Option::none());
    }

    /**
     * Block-and-continue: the agent reads $reason and gets one more turn.
     */
    public static function blocking(string $reason): self
    {
        return new self(Option::some($reason), Option::none());
    }

    /**
     * A non-blocking context injection: the tool/turn proceeds and the agent reads $context. When
     * $quietly, the harness keeps it out of the transcript — a heartbeat the user never sees.
     */
    public static function injecting(string $context, bool $quietly = false): self
    {
        return new self(Option::none(), Option::some($context), $quietly);
    }

    /**
     * Everything the handlers said for one moment, merged into the single response the harness reads.
     *
     * @param  list<self>  $responses  each handler's response, in order
     */
    public static function merge(array $responses): self
    {
        $reasons = [];
        $contexts = [];
        $quietly = true;

        foreach ($responses as $response) {
            foreach ($response->blockReason as $reason) {
                $reasons[] = $reason;
            }

            foreach ($response->context as $context) {
                $contexts[] = $context;
                $quietly = $quietly && $response->suppressOutput;
            }
        }

        if ($reasons !== []) {
            return self::blocking(implode("\n\n", $reasons));
        }

        return $contexts === [] ? self::silent() : self::injecting(implode("\n\n", $contexts), $quietly);
    }

    /**
     * Is there nothing to emit at all?
     */
    public function isSilent(): bool
    {
        return $this->blockReason->isNone() && $this->context->isNone();
    }

    /**
     * This response in the harness's own protocol, encoded — the ONE place the wire shape exists, and
     * it exists only on its way out. Meaningless when {@see isSilent}, which a caller asks first.
     */
    public function json(string $event): string
    {
        foreach ($this->blockReason as $reason) {
            return self::encode(['decision' => 'block', 'reason' => $reason]);
        }

        $injection = ['hookSpecificOutput' => ['hookEventName' => $event, 'additionalContext' => $this->context->unwrapOr('')]];

        return self::encode($this->suppressOutput ? [...$injection, 'suppressOutput' => true] : $injection);
    }

    /**
     * @param  array<string, mixed>  $wire
     */
    private static function encode(array $wire): string
    {
        return json_encode($wire, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }
}
