<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Pilgrimage;

use JesseGall\CodeCommandments\Prophets\Backend\LongMethodProphet;
use JesseGall\CodeCommandments\Prophets\Backend\OptionDisciplineProphet;
use JesseGall\CodeCommandments\Support\Pilgrimage\Pilgrimage;
use JesseGall\CodeCommandments\Support\Pilgrimage\PilgrimageRunner;
use JesseGall\CodeCommandments\Support\Pilgrimage\PilgrimageState;
use PHPUnit\Framework\TestCase;

/**
 * The per-profile / single-prophet pilgrimage: a constrained itinerary, strict
 * prophet resolution, and — the load-bearing guarantee — a completion that can only
 * relax the push gate for the scope it actually covered.
 */
class PerProfilePilgrimageTest extends TestCase
{
    private string $dir;

    private string|false $previousSession;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/cc-perprof-' . uniqid();
        mkdir($this->dir . '/.commandments', 0755, true);
        file_put_contents($this->dir . '/A.php', "<?php\nnamespace App;\nclass A { public function f(): void { \$x = 1; } }\n");
        $this->previousSession = getenv('CLAUDE_CODE_SESSION_ID');
        putenv('CLAUDE_CODE_SESSION_ID=sess-A');
    }

    protected function tearDown(): void
    {
        $this->previousSession === false
            ? putenv('CLAUDE_CODE_SESSION_ID')
            : putenv('CLAUDE_CODE_SESSION_ID=' . $this->previousSession);
        shell_exec('rm -rf ' . escapeshellarg($this->dir));
        parent::tearDown();
    }

    private function profile(string $name): void
    {
        file_put_contents($this->dir . '/.commandments/profile', $name);
    }

    private function runner(): PilgrimageRunner
    {
        return new PilgrimageRunner($this->dir, ['scrolls' => ['backend' => [
            'path' => $this->dir,
            'extensions' => ['php'],
            'prophets' => [OptionDisciplineProphet::class, LongMethodProphet::class],
        ]]], 'backend');
    }

    // ── Constrained itinerary ───────────────────────────────────────────

    public function test_itinerary_collapses_to_one_station_for_a_single_prophet(): void
    {
        $itinerary = Pilgrimage::itinerary(
            [OptionDisciplineProphet::class, LongMethodProphet::class],
            OptionDisciplineProphet::class,
        );

        $this->assertCount(1, $itinerary);
        $this->assertSame([[OptionDisciplineProphet::class]], $itinerary[0]['pillars']);
    }

    // ── Strict resolution ───────────────────────────────────────────────

    public function test_resolve_prophet_strict(): void
    {
        $runner = $this->runner();

        $this->assertSame(OptionDisciplineProphet::class, $runner->resolveProphet('OptionDiscipline')['class']);
        $this->assertSame(OptionDisciplineProphet::class, $runner->resolveProphet('optiondiscipline')['class']);

        $miss = $runner->resolveProphet('ZzzNope');
        $this->assertNull($miss['class']);

        // An ambiguous partial that hits both registered prophets resolves to neither.
        $ambiguous = $runner->resolveProphet('Prophet');
        $this->assertNull($ambiguous['class']);
        $this->assertGreaterThan(1, count($ambiguous['candidates']));
    }

    // ── The no-forge guarantees ─────────────────────────────────────────

    public function test_single_prophet_walk_never_relaxes_the_gate(): void
    {
        // A genuinely-complete single-prophet walk (cursor exhausted, owned) must
        // still report NOT complete to the gate — one rule is not the whole codebase.
        (new PilgrimageState(
            doctrine: 1, complete: true, owner: 'sess-A',
            scopeKind: 'full', onlyProphet: OptionDisciplineProphet::class,
        ))->save($this->dir);

        $this->assertFalse($this->runner()->isComplete(), 'a one-prophet walk can never earn a push past the gate');
    }

    public function test_full_walk_relaxes_only_when_the_profile_scope_still_matches(): void
    {
        $this->profile('penance');   // JudgeScope::None → scopeKind 'full', allowWarnings true

        $total = $this->runner()->totalDoctrines();
        $files = [realpath($this->dir . '/A.php') ?: $this->dir . '/A.php'];

        (new PilgrimageState(
            doctrine: $total, complete: true, owner: 'sess-A',
            scopeKind: 'full', allowWarnings: true, scope: $files,
        ))->save($this->dir);

        $this->assertTrue($this->runner()->isComplete(), 'a completed full walk under penance relaxes the gate');

        // Switch the profile mid-walk: the frozen full descriptor no longer matches
        // the live (staged) gate scope → the stale completion must NOT relax.
        $this->profile('phased');
        $this->assertFalse($this->runner()->isComplete(), 'a profile switch invalidates a stale completion');
    }

    // ── Severity gating (sins-only) ─────────────────────────────────────

    public function test_scan_drops_warnings_when_disallowed(): void
    {
        // OptionDiscipline's always-some case is a WARNING; with allowWarnings=false
        // it must yield nothing (a warning-only prophet is skipped clean under sins-only).
        file_put_contents($this->dir . '/B.php', <<<'PHP'
        <?php
        namespace App;
        use JesseGall\PhpTypes\Option;
        class B { private int $v = 1; public function current(): Option { return Option::some($this->v); } }
        PHP);

        $prophet = new OptionDisciplineProphet();
        $index = new \JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex();
        $prophet->setCodebaseIndex($index);

        $with = (new Pilgrimage)->scanProphet($prophet, [$this->dir . '/B.php'], $index, $this->dir, null, true);
        $without = (new Pilgrimage)->scanProphet($prophet, [$this->dir . '/B.php'], $index, $this->dir, null, false);

        $this->assertNotEmpty($with, 'sanity: the prophet fires as a warning');
        $this->assertEmpty($without, 'sins-only drops the warning — the prophet is skipped clean');
    }
}
