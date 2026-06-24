<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Doctrines;

/**
 * An ordered group of prophets enforcing one architectural truth, coarse → fine.
 *
 * Members are arranged in BANDS (not a strict linear rank): every prophet in a
 * band is a peer — a band can hold several sibling root causes that all suppress
 * the finer bands below without suppressing each other. For a code region, the
 * first band that fires short-circuits every finer band beneath it (the cascade).
 */
final readonly class Doctrine
{
    /**
     * @param  list<list<class-string>>  $bands  coarse (index 0) → fine; each band a peer set
     */
    public function __construct(
        public string $name,
        public array $bands,
    ) {}

    /**
     * The band index (0 = coarsest) of $prophetClass within this doctrine, or
     * null when it is not a member.
     */
    public function bandOf(string $prophetClass): ?int
    {
        foreach ($this->bands as $index => $members) {
            if (in_array($prophetClass, $members, true)) {
                return $index;
            }
        }

        return null;
    }

    public function contains(string $prophetClass): bool
    {
        return $this->bandOf($prophetClass) !== null;
    }

    /**
     * Every member class, flattened (for registration / consistency checks).
     *
     * @return list<class-string>
     */
    public function members(): array
    {
        return array_merge(...$this->bands === [] ? [[]] : $this->bands);
    }
}
