<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\HookMissingComputed;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;

/*
 * Scenario 3 — a numeric total folded over a line-item list, get-hook, no `#[Computed]`. A fold-and-scan
 * shape (largest line, count) distinct from scenarios 1 and 2.
 */
#[Sinful(HookMissingComputed::class)]
final class InvoiceSummary extends Data
{
    public int $total { get => array_sum(array_map(fn (LineItem $i): int => $i->cents, $this->lines)); }

    /**
     * @param list<LineItem> $lines
     */
    public function __construct(public readonly array $lines) {}

    public function largestLine(): ?LineItem
    {
        $largest = null;

        foreach ($this->lines as $line) {
            if ($largest === null || $line->cents > $largest->cents) {
                $largest = $line;
            }
        }

        return $largest;
    }

    public function lineCount(): int
    {
        return count($this->lines);
    }
}
