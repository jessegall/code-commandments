<?php

namespace Shop\Ui;

use JesseGall\CodeCommandments\Sins\Backend\TernaryStatement;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Walks a shelf tree collecting the ids that collapsed — and chooses between recursing and
 * appending with a ternary nothing reads, so an assignment ends up jammed into an expression
 * slot. The righteous twin (`collapsedBranched`) states the same choice as `if`/`else`.
 */
final class ShelfCollapse
{
    /**
     * @param  array<string, array<int, object>>  $below
     * @return array<int, string>
     */
    #[Sinful(TernaryStatement::class)]
    public function collapsed(string $id, array $below): array
    {
        $gone = [];

        foreach ($below[$id] as $child) {
            $this->isOpen($child->id)
                ? array_push($gone, ...$this->collapsed($child->id, $below))
                : $gone[] = $child->id;
        }

        return $gone;
    }

    /**
     * @param  array<string, array<int, object>>  $below
     * @return array<int, string>
     */
    #[Fixed(TernaryStatement::class)]
    #[Righteous(TernaryStatement::class)]
    public function collapsedBranched(string $id, array $below): array
    {
        $gone = [];

        foreach ($below[$id] as $child) {
            if ($this->isOpen($child->id)) {
                array_push($gone, ...$this->collapsedBranched($child->id, $below));
            } else {
                $gone[] = $child->id;
            }
        }

        return $gone;
    }

    private function isOpen(string $id): bool
    {
        return $id !== '';
    }
}
