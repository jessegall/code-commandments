<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Dashboard;

use JsonSerializable;

/**
 * A dashboard in the agent journal's format: a title, the page it opens on, and its pages by id — each a
 * title and a tree of view nodes.
 */
final readonly class Dashboard implements JsonSerializable
{
    /**
     * @param  array<string, array{title: string, view: array<string, mixed>}>  $pages
     */
    public function __construct(
        public string $title,
        public string $start,
        public array $pages,
    ) {}

    /**
     * @return array{title: string, start: string, pages: array<string, array{title: string, view: array<string, mixed>}>}
     */
    public function jsonSerialize(): array
    {
        return ['title' => $this->title, 'start' => $this->start, 'pages' => $this->pages];
    }
}
