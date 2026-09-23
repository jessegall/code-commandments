<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

use JesseGall\CodeCommandments\Detector;
use JesseGall\CodeCommandments\Detectors\Catalog;
use JesseGall\CodeCommandments\Engine;

/**
 * The worked examples every engine's fixture carves out for the skills — the one list the skill
 * generator publishes and the freshness check re-renders, so an engine added to {@see Engine} is
 * published by both at once.
 */
final class SkillExamples
{
    /**
     * $fixtures holds one folder per engine, named as the engine is (`backend`, `frontend`, `python`, `csharp`).
     *
     * @return array<class-string<Detector>, list<Example>>
     */
    public static function from(string $fixtures): array
    {
        $examples = [];

        foreach (Engine::cases() as $engine) {
            $examples += $engine->fixture("{$fixtures}/{$engine->value}", Catalog::of($engine))->examples();
        }

        return $examples;
    }
}
