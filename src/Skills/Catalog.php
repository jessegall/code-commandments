<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills;

use JesseGall\CodeCommandments\Custom;
use JesseGall\CodeCommandments\Discovery;
use JesseGall\CodeCommandments\Languages;

/**
 * Every teaching skill in force for a project — the ones that ship, discovered from the `Backend/`
 * and `Frontend/` folders, PLUS the ones the project wrote into `.commandments/custom/`
 * ({@see Custom::skills}) — the skill twin of {@see \JesseGall\CodeCommandments\Detectors\Catalog}
 * and {@see \JesseGall\CodeCommandments\Sins\Catalog}. A project's own skill is a first-class
 * member: it is published, briefed and counted exactly like a shipped one, so an agent is told it
 * exists (#443). Ordered by each skill's {@see Skill::$order} so the consumer briefing keeps its
 * curated sequence (`fix-at-the-source` first).
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
     * The TypeScript skills — the disciplines of the LANGUAGE, as opposed to the Vue ones, which are
     * about components and templates.
     *
     * Their own tier because the overlap with the backend is partial: TypeScript absence is the same
     * INSTINCT as PHP absence and a different rule set (there is no `Option`, and `undefined` is a
     * second way to be missing), so folding either into the other would make a reader wade through
     * half a document that cannot apply to them.
     *
     * @return list<Skill>
     */
    public static function typescript(): array
    {
        return self::discover('TypeScript');
    }

    /**
     * Every skill in force for $project — every engine and the project's own — in briefing order.
     * $project is the consumer root the custom folder is read from; null resolves the current one.
     *
     * @return list<Skill>
     */
    public static function all(?string $project = null, ?Languages $languages = null): array
    {
        $skills = [...self::backend(), ...self::frontend(), ...self::typescript(), ...Custom::skills($project)];

        usort($skills, static fn (Skill $a, Skill $b): int => $a->order <=> $b->order);

        return self::written($skills, $languages ?? new Languages());
    }

    /**
     * The skills loaded in one tier, in briefing order.
     *
     * @return list<Skill>
     */
    public static function inTier(Tier $tier, ?string $project = null, ?Languages $languages = null): array
    {
        return array_values(array_filter(
            self::all($project, $languages),
            static fn (Skill $skill): bool => $skill->tier === $tier,
        ));
    }

    /**
     * The skills a project can actually use — those teaching at least one language it writes. A
     * discipline whose every language the project has disabled is one it cannot break, so briefing
     * it would describe a codebase that is not this one (#478).
     *
     * @param  list<Skill>  $skills
     * @return list<Skill>
     */
    private static function written(array $skills, Languages $languages): array
    {
        return array_values(array_filter(
            $skills,
            static fn (Skill $skill): bool => array_any($skill->languages(), $languages->writes(...)),
        ));
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
