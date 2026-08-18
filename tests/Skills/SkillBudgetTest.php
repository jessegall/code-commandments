<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Skills;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Catalog as Detectors;
use JesseGall\CodeCommandments\Skills\Catalog as Skills;
use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\SkillRenderer;
use JesseGall\CodeCommandments\Testing\FixtureExamples;
use JesseGall\CodeCommandments\Testing\VueFixtureExamples;
use JesseGall\CodeCommandments\Vue\Codebase as VueCodebase;
use PHPUnit\Framework\TestCase;

/**
 * A skill is read at the moment of writing a line of code, which is the moment its reader has the
 * least room to spare. So the body has a BUDGET, and outgrowing it fails the suite rather than
 * quietly costing every future reader — the fix is to spill into `reference/`, which is what the
 * renderer is shaped to do.
 */
final class SkillBudgetTest extends TestCase
{
    /**
     * The longest a `SKILL.md` may be. Comfortably under the 500 lines the skill-authoring guidance
     * calls the ceiling, with room for the one genuinely large discipline to grow a little before it
     * has to spill.
     */
    private const int LINES = 400;

    /**
     * The longest a frontmatter `description` may be. It is the ONE thing an agent reads for every
     * skill on every turn, so its cost is paid whether the skill is loaded or not.
     */
    private const int DESCRIPTION = 700;

    /**
     * @return array<string, array<string, string>>  skill id => relative path => content
     */
    private function documents(): array
    {
        $root = dirname(__DIR__, 2);
        $examples = FixtureExamples::extract(Codebase::scan("{$root}/tests/Fixtures/backend"), Detectors::backend())
            + VueFixtureExamples::extract(VueCodebase::scan("{$root}/tests/Fixtures/frontend"), Detectors::frontend());
        $renderer = new SkillRenderer();
        $documents = [];

        foreach (Skills::all() as $skill) {
            $documents[$skill->id()] = $renderer->documents($skill, $examples);
        }

        return $documents;
    }

    public function test_no_skill_body_outgrows_its_budget(): void
    {
        foreach ($this->documents() as $id => $documents) {
            $lines = substr_count($documents['SKILL.md'], "\n");

            $this->assertLessThanOrEqual(
                self::LINES,
                $lines,
                "{$id}/SKILL.md is {$lines} lines, over the " . self::LINES . "-line budget. Move the "
                . 'mechanics into a reference document (Skill::references()) rather than growing the body.',
            );
        }
    }

    public function test_every_description_stays_within_its_budget(): void
    {
        foreach (Skills::all() as $skill) {
            $length = strlen($skill->trigger());

            $this->assertLessThanOrEqual(
                self::DESCRIPTION,
                $length,
                "{$skill->id()}'s description is {$length} characters, over the " . self::DESCRIPTION
                . ' budget. It is loaded on every turn whether the skill is or not — say WHEN to reach '
                . 'for the skill and stop.',
            );
        }
    }

    public function test_a_skill_never_says_the_same_thing_twice(): void
    {
        foreach ($this->documents() as $id => $documents) {
            foreach ($documents as $path => $document) {
                $sections = self::sections($document);
                $seen = [];

                foreach ($sections as $heading => $content) {
                    $this->assertArrayNotHasKey(
                        $content,
                        $seen,
                        "{$id}/{$path}: `{$heading}` repeats `" . ($seen[$content] ?? '') . '` word for word. '
                        . 'A reader gains nothing from the second copy and pays for it in context.',
                    );

                    $seen[$content] = $heading;
                }
            }
        }
    }

    /**
     * A document's `##` sections, heading => body. Line-anchored, because the only structure this
     * needs is where one section stops and the next starts.
     *
     * @return array<string, string>
     */
    private static function sections(string $document): array
    {
        $sections = [];
        $heading = '';

        foreach (explode("\n", $document) as $line) {
            if (str_starts_with($line, '## ')) {
                $heading = substr($line, 3);
                $sections[$heading] = '';

                continue;
            }

            if ($heading !== '') {
                $sections[$heading] .= $line . "\n";
            }
        }

        return array_map(trim(...), $sections);
    }

    public function test_the_body_carries_the_commands_that_act_on_its_rules(): void
    {
        foreach ($this->documents() as $id => $documents) {
            $skill = self::skillNamed($id);

            if ($skill === null || $documents === []) {
                continue;
            }

            // A skill with no sins teaches a discipline nothing detects; there is no verb to offer.
            if (! str_contains($documents['SKILL.md'], '## Rules')) {
                continue;
            }

            $this->assertStringContainsString(
                'judge --skill=' . $skill->slug,
                $documents['SKILL.md'],
                "{$id}/SKILL.md teaches rules but never names the command that finds them.",
            );
        }
    }

    private static function skillNamed(string $id): ?Skill
    {
        foreach (Skills::all() as $skill) {
            if ($skill->id() === $id) {
                return $skill;
            }
        }

        return null;
    }
}
