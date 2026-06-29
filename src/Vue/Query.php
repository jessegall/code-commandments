<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

use Closure;

/**
 * The frontend twin of the backend {@see \JesseGall\CodeCommandments\Ast\Query}:
 * the SAME fluent pattern — a selector opens it, `where`/`reject` narrow it (one
 * check per line, ANDed), terminals return rich {@see ElementMatch}es that know
 * their `file:line`. A frontend detector reads exactly like a backend one; only
 * the nodes differ (Vue {@see Element}s instead of PHP).
 *
 * The selector sees the raw {@see Element}; `where`/`reject` see the {@see
 * ElementMatch} (which IS an Element), mirroring the backend.
 */
final class Query
{
    /** @var list<Closure(ElementMatch): bool> */
    private array $filters = [];

    /**
     * @param  Closure(Element): bool  $selector
     */
    public function __construct(
        private readonly Codebase $codebase,
        private readonly Closure $selector,
    ) {}

    /**
     * @param  Closure(ElementMatch): bool  $check
     */
    public function where(Closure $check): self
    {
        $this->filters[] = $check;

        return $this;
    }

    /**
     * @param  Closure(ElementMatch): bool  $check
     */
    public function reject(Closure $check): self
    {
        $this->filters[] = static fn (ElementMatch $match): bool => ! $check($match);

        return $this;
    }

    /**
     * @return list<ElementMatch>
     */
    public function get(): array
    {
        $matches = [];

        foreach ($this->codebase->nodes() as [$element, $sfc]) {
            if (! ($this->selector)($element)) {
                continue;
            }

            $match = new ElementMatch($element, $sfc);

            foreach ($this->filters as $filter) {
                if (! $filter($match)) {
                    continue 2;
                }
            }

            $matches[] = $match;
        }

        return $matches;
    }

    public function first(): ?ElementMatch
    {
        return $this->get()[0] ?? null;
    }

    public function count(): int
    {
        return count($this->get());
    }
}
