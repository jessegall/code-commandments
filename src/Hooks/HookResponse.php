<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks;

use JesseGall\PhpTypes\Option;

/**
 * What the harness is told after every handler for one moment has run. Three shapes and no more:
 * SILENT, a BLOCK carrying the joined reasons, or injected CONTEXT (suppressed from the transcript
 * only when every handler that spoke asked). A block wins over context, because a handler that
 * stops the agent has said the more important thing.
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
     * Nothing to say — the handlers all stayed quiet.
     */
    public static function silent(): self
    {
        return new self(Option::none(), Option::none());
    }

    /**
     * Everything the handlers emitted for one moment, merged into the single response the harness
     * reads.
     *
     * @param  list<array<string, mixed>>  $emitted  each handler's payload, in order
     */
    public static function merge(array $emitted): self
    {
        $emissions = array_map(HookEmission::of(...), $emitted);
        $reasons = [];
        $contexts = [];
        $suppress = true;

        foreach ($emissions as $emission) {
            foreach ($emission->blockReason() as $reason) {
                $reasons[] = $reason;
            }

            foreach ($emission->context() as $context) {
                $contexts[] = $context;
                $suppress = $suppress && $emission->suppressesOutput();
            }
        }

        if ($reasons !== []) {
            return new self(Option::some(implode("\n\n", $reasons)), Option::none());
        }

        return $contexts === []
            ? self::silent()
            : new self(Option::none(), Option::some(implode("\n\n", $contexts)), $suppress);
    }

    /**
     * Is there nothing to emit at all?
     */
    public function isSilent(): bool
    {
        return $this->blockReason->isNone() && $this->context->isNone();
    }

    /**
     * This response in the harness's own protocol — the ONE place the wire shape is written.
     * Meaningless when {@see isSilent}, which a caller asks first.
     *
     * @return array<string, mixed>
     */
    public function payload(string $event): array
    {
        foreach ($this->blockReason as $reason) {
            return ['decision' => 'block', 'reason' => $reason];
        }

        $response = [
            'hookSpecificOutput' => ['hookEventName' => $event, 'additionalContext' => $this->context->unwrapOr('')],
        ];

        return $this->suppressOutput ? [...$response, 'suppressOutput' => true] : $response;
    }
}
