<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Feature;

use Illuminate\Support\Collection;
use JesseGall\CodeCommandments\Prophets\Backend\DuplicateCodeProphet;
use JesseGall\CodeCommandments\Results\Warning;
use JesseGall\CodeCommandments\Scanners\GenericFileScanner;
use JesseGall\CodeCommandments\Support\ProphetRegistry;
use JesseGall\CodeCommandments\Support\ScrollManager;
use JesseGall\CodeCommandments\Tests\TestCase;

class DuplicateCodeProphetTest extends TestCase
{
    private ScrollManager $manager;

    private ProphetRegistry $registry;

    private string $fixtureDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new ProphetRegistry;
        $this->manager = new ScrollManager($this->registry, new GenericFileScanner);

        $this->fixtureDir = realpath(__DIR__ . '/../Fixtures/Backend/Sinful/DuplicateCode');
        $this->assertNotFalse($this->fixtureDir);

        $this->registry->registerMany('test', [DuplicateCodeProphet::class => []]);
        $this->registry->setScrollConfig('test', [
            'path' => $this->fixtureDir,
            'extensions' => ['php'],
        ]);
    }

    public function test_flags_a_method_duplicated_in_another_file_modulo_variable_names(): void
    {
        $results = $this->manager->judgeScroll('test');

        $alpha = $this->firstWarningFor($results, $this->fixtureDir . '/Alpha.php');
        $this->assertNotNull($alpha, 'Alpha::expandRoots is duplicated in Beta — flag it.');
        $this->assertStringContainsString('Duplicated code fragment', $alpha->message);
        $this->assertStringContainsString('expandNodes', $alpha->message);

        $beta = $this->firstWarningFor($results, $this->fixtureDir . '/Beta.php');
        $this->assertNotNull($beta, 'Beta::expandNodes is the other copy — flag it too.');
        $this->assertStringContainsString('expandRoots', $beta->message);
    }

    public function test_flags_a_shared_preamble_between_two_diverging_methods(): void
    {
        // #36: GammaResolver::resolve and DeltaResolver::resolve share a leading
        // preamble (nodeById -> isEmpty guard -> getOrThrow -> findOutput ->
        // isControlHandle guard) then diverge — whole-body hashes differ, but the
        // duplicated prefix must still be caught.
        $results = $this->manager->judgeScroll('test');

        $gamma = $this->firstWarningFor($results, $this->fixtureDir . '/GammaResolver.php');
        $this->assertNotNull($gamma, 'GammaResolver::resolve shares a preamble with DeltaResolver — flag it.');
        $this->assertStringContainsString('Duplicated preamble', $gamma->message);
        $this->assertStringContainsString('resolve', $gamma->message);

        $delta = $this->firstWarningFor($results, $this->fixtureDir . '/DeltaResolver.php');
        $this->assertNotNull($delta, 'DeltaResolver::resolve is the other copy — flag it too.');
        $this->assertStringContainsString('Duplicated preamble', $delta->message);
    }

    public function test_does_not_flag_a_unique_method(): void
    {
        $results = $this->manager->judgeScroll('test');

        $warnings = $this->warningsFor($results, $this->fixtureDir . '/Alpha.php');

        foreach ($warnings as $warning) {
            $this->assertStringNotContainsString('onlyHere', $warning->message);
        }
    }

    public function test_does_not_flag_a_generic_maybe_array_guard_preamble(): void
    {
        // #202: ParseListA/ParseListB share only `if (! is_array($v)) return [];
        // $acc = []; foreach (...)` then diverge inside the loop — idiom, not
        // duplicated logic.
        $results = $this->manager->judgeScroll('test');

        $this->assertEmpty($this->warningsFor($results, $this->fixtureDir . '/ParseListA.php'),
            '#202: a generic is_array-guard + accumulator + foreach preamble must not be flagged.');
        $this->assertEmpty($this->warningsFor($results, $this->fixtureDir . '/ParseListB.php'),
            '#202: a generic is_array-guard + accumulator + foreach preamble must not be flagged.');
    }

    public function test_stays_silent_without_an_index(): void
    {
        // No index injected -> cannot compare cross-file -> silent.
        $prophet = new DuplicateCodeProphet;
        $content = file_get_contents($this->fixtureDir . '/Alpha.php');

        $this->assertTrue($prophet->judge($this->fixtureDir . '/Alpha.php', $content)->isRighteous());
    }

    /**
     * @param  Collection<string, mixed>  $results
     */
    private function firstWarningFor(Collection $results, string $file): ?Warning
    {
        return $this->warningsFor($results, $file)[0] ?? null;
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

        $judgment = $results->get($file)->get(DuplicateCodeProphet::class);

        return $judgment === null ? [] : array_values($judgment->warnings);
    }
}
