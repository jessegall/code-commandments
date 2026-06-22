<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Skills;

use JesseGall\CodeCommandments\Support\Skills\Skill;
use JesseGall\CodeCommandments\Support\Skills\SkillRegistry;
use JesseGall\CodeCommandments\Tests\TestCase;

/**
 * The drift check (twin of RootCauseMapTest): the skill catalogue can't diverge
 * from the prophets it claims to back, and every backend prophet family the
 * issue scopes to v1 is covered by exactly one skill.
 */
class SkillRegistryTest extends TestCase
{
    private const STUB_DIR = __DIR__ . '/../../stubs/skills';

    /** The backend subjects shipped (frontend/Vue is deferred). */
    private const EXPECTED_SLUGS = [
        'option',
        'invariants',
        'registry',
        'set',
        'null-object',
        'enums',
        'named-exceptions',
        'resolvers',
        'coalesce-factories',
        'immutable-data',
        'value-flow',
        'reporting',
    ];

    public function test_catalogue_is_the_backend_subjects(): void
    {
        $slugs = array_map(fn (Skill $s) => $s->slug, SkillRegistry::all());

        $this->assertCount(12, $slugs, 'backend skills.');
        $this->assertSame(self::EXPECTED_SLUGS, $slugs, 'Catalogue slugs/order drifted from the spec.');
    }

    public function test_every_skill_has_a_packaged_stub_directory_with_a_skill_md(): void
    {
        foreach (SkillRegistry::all() as $skill) {
            $dir = self::STUB_DIR . '/' . $skill->stubDir();
            $this->assertDirectoryExists($dir, "Missing stub dir for {$skill->slug}");
            $this->assertFileExists($dir . '/SKILL.md', "Missing SKILL.md for {$skill->slug}");
        }
    }

    public function test_every_skill_names_a_non_empty_prophet_family(): void
    {
        foreach (SkillRegistry::all() as $skill) {
            if ($skill->workflow) {
                // A workflow/command skill (e.g. reporting) teaches a CLI flow,
                // not a prophet family — it legitimately backs no prophets.
                $this->assertSame([], $skill->prophets, "Workflow skill {$skill->slug} should not name prophets.");

                continue;
            }

            $this->assertNotEmpty($skill->prophets, "Skill {$skill->slug} backs no prophets.");
        }
    }

    public function test_skill_name_is_namespaced_under_the_package(): void
    {
        foreach (SkillRegistry::all() as $skill) {
            $this->assertSame('commandments-' . $skill->slug, $skill->skillName());
        }
    }

    public function test_no_prophet_is_claimed_by_two_skills(): void
    {
        $seen = [];

        foreach (SkillRegistry::all() as $skill) {
            foreach ($skill->prophets as $prophet) {
                $other = $seen[$prophet] ?? null;
                $this->assertArrayNotHasKey($prophet, $seen, "{$prophet} is backed by two skills ({$other} and {$skill->slug}).");
                $seen[$prophet] = $skill->slug;
            }
        }
    }

    public function test_every_named_prophet_resolves_to_a_real_backend_prophet_class(): void
    {
        // The drift guard: a prophet short-name in the catalogue must map to an
        // actual prophet class file, so the catalogue can't reference a renamed
        // or deleted prophet.
        $prophetDir = __DIR__ . '/../../src/Prophets/Backend';

        foreach (SkillRegistry::all() as $skill) {
            foreach ($skill->prophets as $prophet) {
                $candidates = [
                    $prophetDir . '/' . $prophet . 'Prophet.php',
                    $prophetDir . '/' . $prophet . '.php',
                ];

                $exists = array_filter($candidates, 'is_file') !== [];

                $this->assertTrue($exists, "Skill {$skill->slug} names prophet {$prophet}, but no matching class exists under src/Prophets/Backend.");
            }
        }
    }

    public function test_slug_for_prophet_maps_both_short_and_suffixed_names(): void
    {
        // The prophet → skill pointer must work whether passed the bare short
        // name or the `*Prophet` class basename (which is what BaseCommandment
        // hands it via static::class).
        $this->assertSame('option', SkillRegistry::slugForProphet('NoOptionToNull'));
        $this->assertSame('option', SkillRegistry::slugForProphet('NoOptionToNullProphet'));
        $this->assertSame('option', SkillRegistry::slugForProphet('JesseGall\\CodeCommandments\\Prophets\\Backend\\NoOptionToNullProphet'));
        $this->assertSame('enums', SkillRegistry::slugForProphet('StringsThatShouldBeEnumsProphet'));
        $this->assertSame('registry', SkillRegistry::slugForProphet('RegistryReturnContractProphet'));
        $this->assertNull(SkillRegistry::slugForProphet('SomeProphetWithNoSkill'));
    }
}
