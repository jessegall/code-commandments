<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Hooks;

use JsonSerializable;

/**
 * What the plugin answers the journal for one moment: a reason to `refuse` the call, a `whisper` for
 * the agent alone, and the events to `raise` — each only when there is one.
 */
final readonly class JournalAnswer implements JsonSerializable
{
    public function __construct(
        public ?string $refuse = null,
        public ?string $whisper = null,
        /**
         * @var list<JournalRaise>
         */
        public array $raises = [],
    ) {}

    public function raising(JournalRaise ...$raises): self
    {
        return clone($this, ['raises' => [...$this->raises, ...array_values($raises)]]);
    }

    /**
     * The answer as the journal reads it — one JSON object, `{}` when there is nothing to say.
     */
    public function toJson(): string
    {
        return json_encode($this, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * An object always, `raise` a list the journal takes whole.
     */
    public function jsonSerialize(): object
    {
        return (object) array_filter(['refuse' => $this->refuse, 'whisper' => $this->whisper, 'raise' => $this->raises], static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /**
     * What this answer tells the agent, as title and text pairs — its refusal or whisper. The events it
     * raises are not said; they are raised.
     *
     * @return list<array{string, string}>
     */
    public function said(): array
    {
        $told = $this->refuse ?? $this->whisper;

        return $told === null ? [] : [[$told, $told]];
    }
}
