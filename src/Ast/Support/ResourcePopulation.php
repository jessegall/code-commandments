<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

/**
 * What one POPULATION of units reaches, and how rare each resource is within it. A population is a
 * granularity — every class, or every method — and rarity only means anything against the units it was
 * counted over, so the two travel together rather than a caller pairing a reach with the wrong denominator.
 */
final class ResourcePopulation
{
    /**
     * @param  array<string, array<string, true>>  $reach  unit => every resource it reaches
     * @param  array<string, int>  $holders  resource => how many units reach it
     */
    public function __construct(
        private readonly array $reach,
        private readonly array $holders,
    ) {}

    /**
     * @return array<string, true>
     */
    public function of(?string $unit): array
    {
        return $this->reach[(string) $unit] ?? [];
    }

    public function holdersOf(string $resource): int
    {
        return $this->holders[$resource] ?? 0;
    }

    /**
     * How much $resource TELLS us — its inverse frequency here. A verb three units reach all but names
     * a mechanism; one half of them reach names nothing, and no number of those adds up to a finding.
     */
    public function weightOf(string $resource): float
    {
        return log(max(2, count($this->reach)) / max(1, $this->holdersOf($resource)));
    }

    /**
     * The reach of $unit worth comparing: resources held by at most $maxShare of the population, rarest
     * first. Anything more widespread is the program's background and says nothing about what this unit
     * DOES. A SHARE rather than a count, so the answer means the same thing in a small project and a
     * monorepo — a raw count is 80% of one population and 0.8% of another, and a rule whose verdict
     * turns on which it was cannot be proven anywhere.
     *
     * @param  float  $maxShare  a fraction of the population, e.g. 0.05 for "held by at most one in twenty"
     * @return list<string>
     */
    public function rareOf(?string $unit, float $maxShare): array
    {
        // Never below two: a mechanism only exists by RECURRING, so a resource held by two units is
        // the least a shared one can be. A ceiling under that filters out the very thing being looked
        // for, and a small program — a unit test's, a young project's — has no background to speak of.
        $ceiling = max(2, (int) ceil($maxShare * count($this->reach)));
        $rare = [];

        foreach (array_keys($this->of($unit)) as $resource) {
            if ($this->holdersOf($resource) <= $ceiling) {
                $rare[] = $resource;
            }
        }

        // Rarest first, and by name where two are equally rare, so the same set always reads the same way.
        usort($rare, fn (string $a, string $b): int
            => [$this->holdersOf($a), $a] <=> [$this->holdersOf($b), $b]);

        return $rare;
    }
}
