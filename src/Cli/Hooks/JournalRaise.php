<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Hooks;

use JsonSerializable;

/**
 * An event the plugin raises on the journal's bus — `sin-found` or `sin-resolved` — with its brief.
 */
final readonly class JournalRaise implements JsonSerializable
{
    public function __construct(
        public string $event,
        public string $brief,
    ) {}

    /**
     * @return array{event: string, brief: string}
     */
    public function jsonSerialize(): array
    {
        return ['event' => $this->event, 'brief' => $this->brief];
    }

    /**
     * What this event says in one line, for a message that carries it.
     */
    public function headline(): string
    {
        return $this->event === 'sin-resolved' ? 'Code Commandments — a sin in the file you changed is gone' : 'Code Commandments — a sin in the file you changed';
    }
}
