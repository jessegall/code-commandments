<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Hooks;

use JsonSerializable;

/**
 * An event the plugin raises on the journal's bus — `sin-found` or `sin-resolved` — with its brief, and the
 * dashboard page its chat mark opens when it has one.
 */
final readonly class JournalRaise implements JsonSerializable
{
    public function __construct(
        public string $event,
        public string $brief,
        public ?string $open = null,
    ) {}

    /**
     * @return array{event: string, brief: string, open?: string}
     */
    public function jsonSerialize(): array
    {
        return array_filter(['event' => $this->event, 'brief' => $this->brief, 'open' => $this->open], static fn (?string $value): bool => $value !== null);
    }
}
