<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\HookMissingComputed;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;

/*
 * Scenario 2 — a computed string headline over own fields, get-hook, no `#[Computed]`. A string-composition
 * shape with its own slug/reading helpers, distinct from the collaborator-list hook of scenario 1.
 */
#[Sinful(HookMissingComputed::class)]
final class ArticleHeader extends Data
{
    public string $headline { get => trim($this->title) . ' — ' . $this->author; }

    public function __construct(
        public readonly string $title,
        public readonly string $author,
        public readonly int $readingMinutes,
    ) {}

    public function slug(): string
    {
        return strtolower(str_replace(' ', '-', trim($this->title)));
    }

    public function isLongRead(): bool
    {
        return $this->readingMinutes >= 10;
    }
}
