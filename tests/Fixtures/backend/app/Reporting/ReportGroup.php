<?php

namespace Shop\Reporting;

use Illuminate\Contracts\Support\Arrayable;
use JesseGall\CodeCommandments\Sins\Backend\ArrayReturnBag;
use JesseGall\CodeCommandments\Testing\Righteous;

final class ReportGroup implements Arrayable
{
    /**
     * @param list<string> $sections
     */
    public function __construct(
        private readonly string $title,
        private readonly array $sections,
    ) {}

    #[Righteous(ArrayReturnBag::class)]
    public function toArray(): array
    {
        return [
            'type' => 'group',
            'title' => $this->heading(),
            'sections' => $this->sectionCount(),
        ];
    }

    private function heading(): string
    {
        return ucfirst($this->title);
    }

    private function sectionCount(): int
    {
        return count($this->sections);
    }
}
