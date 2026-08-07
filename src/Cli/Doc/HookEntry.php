<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Doc;

/**
 * One built-in hook as the docs describe it: what it is called, the events it binds, and the
 * one-line summary it declares. What {@see HookCatalog} lists, so a table row and the test that
 * checks a hook documents itself read the same three facts by name.
 */
final readonly class HookEntry
{
    public function __construct(
        public string $name,
        public string $events,
        public string $summary,
    ) {}

    /**
     * This entry as a markdown table row — the `|` in a summary escaped, since it would otherwise
     * open a column.
     */
    public function row(): string
    {
        $summary = str_replace('|', '\|', $this->summary);

        return "| `{$this->name}` | `{$this->events}` | {$summary} |\n";
    }
}
