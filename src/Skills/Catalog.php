<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills;

use JesseGall\CodeCommandments\Discovery;

/**
 * Every teaching skill that ships, discovered from the `Backend/` and `Frontend/`
 * folders — the skill twin of {@see \JesseGall\CodeCommandments\Detectors\Catalog}
 * and {@see \JesseGall\CodeCommandments\Sins\Catalog}. Ordered by each skill's
 * {@see Skill::$order} so the consumer briefing keeps its curated sequence
 * (`fix-at-the-source` first). A consumer's own `Skills/` class auto-enrols.
 */
final class Catalog
{
    /**
     * The backend (PHP/Laravel) skills.
     *
     * @return list<Skill>
     */
    public static function backend(): array
    {
        return self::discover('Backend');
    }

    /**
     * The frontend (Vue) skills.
     *
     * @return list<Skill>
     */
    public static function frontend(): array
    {
        return self::discover('Frontend');
    }

    /**
     * Every skill, both engines, in briefing order.
     *
     * @return list<Skill>
     */
    public static function all(): array
    {
        $skills = [...self::backend(), ...self::frontend()];

        usort($skills, static fn (Skill $a, Skill $b): int => $a->order <=> $b->order);

        return $skills;
    }

    /**
     * The skills loaded in one tier, in briefing order.
     *
     * @return list<Skill>
     */
    public static function inTier(Tier $tier): array
    {
        return array_values(array_filter(self::all(), static fn (Skill $skill): bool => $skill->tier === $tier));
    }

    /**
     * @return list<Skill>
     */
    private static function discover(string $engine): array
    {
        $skills = [];

        foreach (Discovery::classes(__DIR__ . "/{$engine}", __NAMESPACE__ . "\\{$engine}") as $class) {
            if (is_subclass_of($class, Skill::class)) {
                $skills[] = new $class;
            }
        }

        return $skills;
    }
}
