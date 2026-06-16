<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use Illuminate\Support\Collection;
use JesseGall\CodeCommandments\Prophets\Backend\PreferEnumCaseGroupsProphet;
use JesseGall\CodeCommandments\Results\Warning;
use JesseGall\CodeCommandments\Scanners\GenericFileScanner;
use JesseGall\CodeCommandments\Support\ProphetRegistry;
use JesseGall\CodeCommandments\Support\ScrollManager;
use JesseGall\CodeCommandments\Tests\TestCase;

class PreferEnumCaseGroupsProphetTest extends TestCase
{
    private ScrollManager $manager;

    private ProphetRegistry $registry;

    private string $fixtureDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new ProphetRegistry;
        $this->manager = new ScrollManager($this->registry, new GenericFileScanner);

        $this->fixtureDir = realpath(__DIR__ . '/../../../Fixtures/Backend/Sinful/PreferEnumCaseGroups');
        $this->assertNotFalse($this->fixtureDir);

        $this->registry->registerMany('test', [
            PreferEnumCaseGroupsProphet::class => [],
        ]);
        $this->registry->setScrollConfig('test', [
            'path' => $this->fixtureDir,
            'extensions' => ['php'],
        ]);
    }

    public function test_flags_a_three_case_group_duplicated_across_two_files_at_both_sites(): void
    {
        $results = $this->manager->judgeScroll('test');

        $warningA = $this->firstWarningFor($results, $this->fixtureDir . '/NumericSiteA.php');
        $this->assertNotNull($warningA, 'NumericSiteA should be flagged.');
        $this->assertStringContainsString('CompareOperator', $warningA->message);
        $this->assertStringContainsString('inlined in 2 sites', $warningA->message);
        $this->assertStringContainsString('CompareOperator::someGroup', $warningA->message);

        $warningB = $this->firstWarningFor($results, $this->fixtureDir . '/NumericSiteB.php');
        $this->assertNotNull($warningB, 'NumericSiteB should be flagged (different order, same group).');
        $this->assertStringContainsString('inlined in 2 sites', $warningB->message);
    }

    public function test_flags_a_single_inline_group_on_sight(): void
    {
        // min_reuse defaults to 1: a nameable inline group is flagged even when
        // it appears exactly once. The reuse note is omitted (no other sites).
        $results = $this->manager->judgeScroll('test');

        $warning = $this->firstWarningFor($results, $this->fixtureDir . '/OneOff.php');
        $this->assertNotNull($warning, 'A single 3-case group must be flagged on sight.');
        $this->assertStringContainsString('CompareOperator', $warning->message);
        $this->assertStringContainsString('named concept in disguise', $warning->message);
        $this->assertStringNotContainsString('sites', $warning->message);
    }

    public function test_min_reuse_override_restores_duplicate_only_flagging(): void
    {
        // Raising min_reuse back to 2 makes a single inline group a one-off
        // again — the opt-in "only flag real duplicates" mode.
        $this->registry->registerMany('test', [
            PreferEnumCaseGroupsProphet::class => ['min_reuse' => 2],
        ]);

        $results = $this->manager->judgeScroll('test');

        $this->assertNull(
            $this->firstWarningFor($results, $this->fixtureDir . '/OneOff.php'),
            'With min_reuse=2 a single inline group is a one-off and must not be flagged.',
        );
    }

    public function test_does_not_flag_a_two_case_array_below_the_threshold(): void
    {
        $results = $this->manager->judgeScroll('test');

        $warnings = $this->warningsFor($results, $this->fixtureDir . '/EdgeCases.php');

        foreach ($warnings as $warning) {
            $this->assertStringNotContainsString(
                'NotEquals]',
                $warning->message,
                'A 2-case group must never be flagged, even when duplicated.',
            );
        }
    }

    public function test_does_not_flag_an_array_mixing_two_enums(): void
    {
        $results = $this->manager->judgeScroll('test');

        $warnings = $this->warningsFor($results, $this->fixtureDir . '/EdgeCases.php');

        foreach ($warnings as $warning) {
            $this->assertStringNotContainsString(
                'Alpha',
                $warning->message,
                'A mixed-enum array is not a single named group and must not be flagged.',
            );
        }
    }

    public function test_does_not_flag_an_in_array_membership_haystack(): void
    {
        $results = $this->manager->judgeScroll('test');

        // The numeric group inside in_array(...) in EdgeCases must not be
        // flagged — that membership test belongs to the CompareSelf rule.
        $warnings = $this->warningsFor($results, $this->fixtureDir . '/EdgeCases.php');

        $this->assertSame(
            [],
            $warnings,
            'EdgeCases has only below-threshold, mixed, and in_array groups — none should be flagged.',
        );
    }

    public function test_does_not_flag_a_group_inside_the_enums_own_file(): void
    {
        $results = $this->manager->judgeScroll('test');

        $this->assertNull(
            $this->firstWarningFor($results, $this->fixtureDir . '/CompareOperator.php'),
            'The numeric() accessor in the enum\'s own file is the named home, not a duplicate.',
        );
    }

    public function test_flags_in_single_file_mode_without_an_index(): void
    {
        // The rule is local: even with no codebase index injected, a nameable
        // inline group is flagged. Without the index there is no reuse count,
        // so the finding carries no "N sites" note.
        $prophet = new PreferEnumCaseGroupsProphet;
        $file = $this->fixtureDir . '/NumericSiteA.php';
        $content = file_get_contents($file);

        $judgment = $prophet->judge($file, $content);

        $this->assertTrue($judgment->isFallen() || $judgment->warningCount() > 0);
        $warning = array_values($judgment->warnings)[0] ?? null;
        $this->assertNotNull($warning);
        $this->assertStringContainsString('CompareOperator', $warning->message);
        $this->assertStringNotContainsString('sites', $warning->message);
    }

    /**
     * @param  Collection<string, mixed>  $results
     */
    private function firstWarningFor(Collection $results, string $file): ?Warning
    {
        $warnings = $this->warningsFor($results, $file);

        return $warnings[0] ?? null;
    }

    /**
     * @param  Collection<string, mixed>  $results
     * @return list<Warning>
     */
    private function warningsFor(Collection $results, string $file): array
    {
        if (! $results->has($file)) {
            return [];
        }

        $judgment = $results->get($file)->get(PreferEnumCaseGroupsProphet::class);

        if ($judgment === null) {
            return [];
        }

        return array_values($judgment->warnings);
    }
}
