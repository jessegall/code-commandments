<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\Evals;

/**
 * What a trigger run measured, per skill: how often its own prompts pulled it, and how often it
 * answered a prompt that belonged to someone else. The second number is the one worth having —
 * thirty descriptions all competing for the same words is how a set of skills stops discriminating.
 */
final class Scorecard
{
    /**
     * @var array<string, array{pulled: int, owned: int, stolen: int, misses: list<string>}>
     */
    private array $rows = [];

    public function record(TriggerQuery $query, string $skill, bool $consulted): void
    {
        $row = $this->rows[$skill] ??= ['pulled' => 0, 'owned' => 0, 'stolen' => 0, 'misses' => []];

        if ($query->owner === $skill) {
            $row['owned']++;
            $row['pulled'] += $consulted ? 1 : 0;
        } elseif ($consulted) {
            $row['stolen']++;
        }

        if (! $query->isAnsweredBy($consulted ? [$skill] : [], $skill)) {
            $row['misses'][] = $query->query;
        }

        $this->rows[$skill] = $row;
    }

    /**
     * @return array<string, array{pulled: int, owned: int, stolen: int, misses: list<string>}>
     */
    public function rows(): array
    {
        ksort($this->rows);

        return $this->rows;
    }

    /**
     * The share of a skill's OWN prompts that pulled it — 1.0 when its description never missed one.
     * A skill with no prompts has nothing measured, and says so as 0.0 rather than pretending to 1.0.
     */
    public function recall(string $skill): float
    {
        $row = $this->rows[$skill] ?? null;

        return $row === null || $row['owned'] === 0 ? 0.0 : $row['pulled'] / $row['owned'];
    }

    /**
     * How many prompts belonging to another skill this one answered anyway.
     */
    public function collisions(string $skill): int
    {
        return $this->rows[$skill]['stolen'] ?? 0;
    }

    /**
     * Did every measured skill pull at least $floor of its own prompts, and steal none? The gate a
     * `checks` step would read.
     */
    public function isClean(float $floor = 1.0): bool
    {
        foreach ($this->rows() as $skill => $row) {
            if ($row['owned'] > 0 && ($this->recall($skill) < $floor || $row['stolen'] > 0)) {
                return false;
            }
        }

        return true;
    }
}
