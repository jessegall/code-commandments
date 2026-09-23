<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Hooks;

use JsonSerializable;

/**
 * What the plugin answers the journal for one moment: a reason to `refuse` the call, a `whisper` for
 * the agent alone, and an event to `raise` — each only when there is one.
 */
final readonly class JournalAnswer implements JsonSerializable
{
    public function __construct(
        public ?string $refuse = null,
        public ?string $whisper = null,
        public ?JournalRaise $raise = null,
    ) {}

    public function raising(JournalRaise $raise): self
    {
        return clone($this, ['raise' => $raise]);
    }

    /**
     * The answer as the journal reads it — one JSON object, `{}` when there is nothing to say.
     */
    public function toJson(): string
    {
        return json_encode($this, JSON_FORCE_OBJECT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return array_filter(['refuse' => $this->refuse, 'whisper' => $this->whisper, 'raise' => $this->raise], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * What this answer tells the agent, as title and text pairs — its refusal or whisper. The event it
     * raises is not said; it is raised.
     *
     * @return list<array{string, string}>
     */
    public function said(): array
    {
        $told = $this->refuse ?? $this->whisper;

        return $told === null ? [] : [[$told, $told]];
    }
}
