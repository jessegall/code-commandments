<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast;

use JesseGall\CodeCommandments\WholeTree;

/**
 * What a run of a detector DREW ON beyond the file in hand — every class-like name it resolved, and
 * whether it has read the whole tree ({@see WholeTree}). A verdict may be reused while everything it was
 * drawn from is unchanged, so this is what a cached verdict is keyed on.
 */
final class Resolutions
{
    /**
     * @var array<string, true>  the class-like names resolved, as a set
     */
    private array $names = [];

    private bool $wholeTree = false;

    /**
     * Note that an answer was drawn from the declaration of $fqcn.
     */
    public function record(?string $fqcn): void
    {
        if ($fqcn === null || $fqcn === '') {
            return;
        }

        $this->names[ltrim($fqcn, '\\')] = true;
    }

    /**
     * Note that an answer was drawn from EVERY file — a call graph, a value flow, a "does anything
     * else in the codebase do X". No smaller set can stand in for it.
     */
    public function recordWholeTree(): void
    {
        $this->wholeTree = true;
    }

    /**
     * @return list<string>  the class-like names resolved, in the order first asked for
     */
    public function names(): array
    {
        return array_keys($this->names);
    }

    public function hasReadWholeTree(): bool
    {
        return $this->wholeTree;
    }
}
