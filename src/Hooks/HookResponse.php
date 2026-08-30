<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks;

use JesseGall\PhpTypes\Option;

/**
 * What the harness is told after a hook moment — four shapes and no more: SILENT, a BLOCK carrying a
 * reason, injected CONTEXT (kept out of the transcript when the handler asked), or the compaction
 * INSTRUCTIONS a `PreCompact` writes. A block wins over both, and this is the ONE place the wire shape is
 * written ({@see json}), so a handler names what it wants to say and never the protocol's own keys.
 */
final readonly class HookResponse
{
    /**
     * @param  Option<string>  $blockReason
     * @param  Option<string>  $context
     * @param  Option<string>  $instructions
     */
    private function __construct(
        public Option $blockReason,
        public Option $context,
        public bool $suppressOutput,
        public Option $instructions,
    ) {}

    /**
     * Nothing to say.
     */
    public static function silent(): self
    {
        return new self(Option::none(), Option::none(), false, Option::none());
    }

    /**
     * The instructions a compaction is to be summarised UNDER — the one thing a `PreCompact` says besides
     * blocking. The harness takes such a hook's stdout verbatim as `newCustomInstructions`, so this shape
     * travels as raw text ({@see json}).
     */
    public static function instructing(string $text): self
    {
        $text = trim($text);

        return $text === ''
            ? self::silent() // The harness keeps only non-empty output; saying nothing is the same answer, plainly.
            : new self(Option::none(), Option::none(), false, Option::some($text));
    }

    /**
     * Block-and-continue: the agent reads $reason and gets one more turn.
     */
    public static function blocking(string $reason): self
    {
        return new self(Option::some($reason), Option::none(), false, Option::none());
    }

    /**
     * A non-blocking context injection: the tool/turn proceeds and the agent reads $context. When
     * $quietly, the harness keeps it out of the transcript — a heartbeat the user never sees.
     */
    public static function injecting(string $context, bool $quietly = false): self
    {
        return new self(Option::none(), Option::some($context), $quietly, Option::none());
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
        $instructions = [];
        $quietly = true;

        foreach ($responses as $response) {
            foreach ($response->blockReason as $reason) {
                $reasons[] = $reason;
            }

            foreach ($response->context as $context) {
                $contexts[] = $context;
                $quietly = $quietly && $response->suppressOutput;
            }

            foreach ($response->instructions as $text) {
                $instructions[] = $text;
            }
        }

        // A refusal does NOT silence the rest. One hook blocking used to drop every other hook's
        // context on that call, so the cardinal rule vanished on exactly the calls where the agent had
        // gone longest without recording anything — the moment it was most owed. A block is a verdict
        // about the call; it is not a claim that nobody else had something to say.
        if ($reasons !== []) {
            return new self(
                Option::some(implode("\n\n", $reasons)),
                $contexts === [] ? Option::none() : Option::some(implode("\n\n", $contexts)),
                false,
                Option::none(),
            );
        }

        if ($instructions !== []) {
            return self::instructing(implode("\n\n", $instructions)); // The harness joins several hooks' output the same way.
        }

        return $contexts === [] ? self::silent() : self::injecting(implode("\n\n", $contexts), $quietly);
    }

    /**
     * Is there nothing to emit at all?
     */
    public function isSilent(): bool
    {
        return $this->blockReason->isNone() && $this->context->isNone() && $this->instructions->isNone();
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

        foreach ($this->instructions as $text) {
            return $text; // RAW: the harness reads a PreCompact's stdout as the instructions themselves.
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
