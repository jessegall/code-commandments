<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

/**
 * The references between the parts of a codebase, as arrows — which part uses which, and the pairs of parts
 * that use each other. The one reading of it every engine shares.
 */
final readonly class DependencyArrows
{
    /**
     * @param  list<DependencyArrow>  $all  in the order the files are written
     */
    public function __construct(
        public array $all,
    ) {}

    /**
     * Does some reference in $from reach $to?
     */
    public function has(string $from, string $to): bool
    {
        return array_any($this->all, static fn (DependencyArrow $arrow): bool => $arrow->from === $from && $arrow->to === $to);
    }

    /**
     * Each part the codebase references another from, with the parts it references.
     *
     * @return array<string, list<string>>
     */
    public function references(): array
    {
        $references = [];

        foreach ($this->all as $arrow) {
            $references[$arrow->from][$arrow->to] = true;
        }

        return array_map(static fn (array $targets): array => array_keys($targets), $references);
    }

    /**
     * The references of the direction worth cutting in every pair of parts that use each other — the thinner
     * of the two, the one with fewer references, ties broken on the name so a codebase always yields the same
     * answer. One per file and part it reaches, each where it is written.
     *
     * @return list<Located>
     */
    public function closingAMutualPair(): array
    {
        $count = [];

        foreach ($this->all as $arrow) {
            $count["{$arrow->from}\0{$arrow->to}"] = ($count["{$arrow->from}\0{$arrow->to}"] ?? 0) + 1;
        }

        $closing = [];

        foreach ($this->all as $arrow) {
            $back = $count["{$arrow->to}\0{$arrow->from}"] ?? 0;

            if ($back > 0 && ([$count["{$arrow->from}\0{$arrow->to}"], $arrow->from] <=> [$back, $arrow->to]) <= 0) {
                $closing[$arrow->at->file() . "\0" . $arrow->to] ??= $arrow->at;
            }
        }

        return array_values($closing);
    }
}
