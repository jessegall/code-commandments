<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Skills;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Catalog as Detectors;
use JesseGall\CodeCommandments\Skills\Catalog as Skills;
use JesseGall\CodeCommandments\Skills\SkillRenderer;
use JesseGall\CodeCommandments\Testing\FixtureExamples;
use JesseGall\CodeCommandments\Testing\VueFixtureExamples;
use JesseGall\CodeCommandments\Vue\Codebase as VueCodebase;
use PHPUnit\Framework\TestCase;

/**
 * Every `SKILL.md` is GENERATED from the catalog (`composer sins`) — the skill class
 * (entry descriptor + body + related) and its sins, whose bad → good examples come
 * from the fixture (`#[Sinful]` + `#[Righteous]`). This locks "generated == committed":
 * edit a skill/sin/fixture without regenerating and this fails, so the docs can never
 * drift from the detectors.
 */
final class GeneratedSkillsAreCurrentTest extends TestCase
{
    public function test_every_skill_md_matches_the_generated_output(): void
    {
        $root = dirname(__DIR__, 2);
        $examples = FixtureExamples::extract(Codebase::scan("{$root}/tests/Fixtures/backend"), Detectors::backend())
            + VueFixtureExamples::extract(VueCodebase::scan("{$root}/tests/Fixtures/frontend"), Detectors::frontend());
        $renderer = new SkillRenderer();

        foreach (Skills::all() as $skill) {
            $dir = "{$root}/skills/commandments/{$skill->slug}";
            $files = [];

            foreach ($renderer->documents($skill, $examples) as $relative => $rendered) {
                $files["{$dir}/{$relative}"] = $rendered;
            }

            foreach ($files as $path => $rendered) {
                $this->assertFileExists($path);
                $this->assertSame(
                    $rendered,
                    file_get_contents($path),
                    substr($path, strlen("{$root}/skills/commandments/")) . " is stale — run `composer sins`.",
                );
            }

            // A reference document the renderer no longer emits is a leftover the publisher would
            // copy and a reader would trust. The generator deletes them; this is what proves it did.
            foreach (glob("{$dir}/reference/*.md") ?: [] as $path) {
                $this->assertArrayHasKey(
                    $path,
                    $files,
                    substr($path, strlen("{$root}/skills/commandments/")) . " is orphaned — run `composer sins`.",
                );
            }
        }
    }
}
