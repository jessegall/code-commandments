<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support;

use Illuminate\Filesystem\Filesystem;
use JesseGall\CodeCommandments\Prophets\Backend\NoRawRequestProphet;
use JesseGall\CodeCommandments\Prophets\Backend\OptionDisciplineProphet;
use JesseGall\CodeCommandments\Results\Finding;
use JesseGall\CodeCommandments\Scanners\GenericFileScanner;
use JesseGall\CodeCommandments\Support\Absolver;
use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Support\FindingCollector;
use JesseGall\CodeCommandments\Support\ProphetRegistry;
use JesseGall\CodeCommandments\Support\ScrollManager;
use JesseGall\CodeCommandments\Tracking\JsonConfessionTracker;
use JesseGall\CodeCommandments\Tests\TestCase;

class AbsolverTest extends TestCase
{
    private string $dir;
    private string $tablet;
    private ProphetRegistry $registry;
    private ScrollManager $manager;
    private JsonConfessionTracker $tracker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir() . '/cc-absolver-' . uniqid();
        mkdir($this->dir);
        Environment::setBasePath($this->dir);

        // A warning (PreferOption, 3 callers) and a sin (raw request input in a
        // controller) in one file.
        file_put_contents($this->dir . '/ServiceController.php', <<<'PHP'
        <?php
        namespace App;
        use Illuminate\Http\Request;
        class ServiceController {
            public function findRef(array $edges): mixed {
                foreach ($edges as $e) { if ($e) { return $e; } }
                return null;
            }
            public function a(): void { if ($this->findRef([]) === null) { return; } }
            public function b(): void { $x = $this->findRef([]); if ($x === null) { return; } }
            public function c(): void { $r = $this->findRef([]); if ($r !== null) { echo $r; } }
            public function store(Request $request): mixed { return $request->input('name'); }
        }
        PHP);

        $this->tablet = $this->dir . '/.commandments/confessions.json';

        $this->registry = new ProphetRegistry();
        $this->registry->registerMany('backend', [
            OptionDisciplineProphet::class,
            NoRawRequestProphet::class,
        ]);
        $this->registry->setScrollConfig('backend', [
            'path' => $this->dir,
            'extensions' => ['php'],
            'exclude' => [],
            'prophets' => [OptionDisciplineProphet::class, NoRawRequestProphet::class],
        ]);

        $this->manager = new ScrollManager($this->registry, new GenericFileScanner());
        $this->tracker = new JsonConfessionTracker($this->tablet, new Filesystem());
    }

    protected function tearDown(): void
    {
        @unlink($this->dir . '/ServiceController.php');
        @unlink($this->tablet);
        @rmdir($this->dir . '/.commandments');
        @rmdir($this->dir);
        parent::tearDown();
    }

    /**
     * @return list<Finding>
     */
    private function findings(): array
    {
        $collector = new FindingCollector($this->tracker);

        return $collector->collect($this->manager->judgeScroll('backend'), null, markSeen: false);
    }

    private function findingOfKind(string $kind): Finding
    {
        foreach ($this->findings() as $finding) {
            if ($finding->kind === $kind) {
                return $finding;
            }
        }

        $this->fail("No {$kind} finding produced by fixture.");
    }

    public function test_absolves_a_warning_with_a_reason(): void
    {
        $warning = $this->findingOfKind('warning');

        $result = (new Absolver($this->manager, $this->registry, $this->tracker))
            ->absolve($warning->fingerprint, 'only an internal helper, callers are local');

        $this->assertSame(Absolver::STATUS_OK, $result['status']);
        $this->assertTrue($this->tracker->isFindingAbsolved($warning->fingerprint));
    }

    public function test_refuses_to_absolve_a_sin(): void
    {
        $sin = $this->findingOfKind('sin');

        $result = (new Absolver($this->manager, $this->registry, $this->tracker))
            ->absolve($sin->fingerprint, 'I do not want to fix it');

        $this->assertSame(Absolver::STATUS_ERROR, $result['status']);
        $this->assertStringContainsString('must be FIXED', $result['message']);
        $this->assertFalse($this->tracker->isFindingAbsolved($sin->fingerprint));
    }

    public function test_reporting_a_sin_absolves_it_and_drops_it_from_the_queue(): void
    {
        // Reporting a sin as wrong records a report-linked absolution directly —
        // the sin goes quiet (no separate absolve needed) and no longer surfaces.
        $sin = $this->findingOfKind('sin');
        $this->assertFalse($this->tracker->isFindingAbsolved($sin->fingerprint));

        $this->tracker->reportFinding($sin->fingerprint, 'genuine false positive', 99, 'owner/repo');

        $this->assertTrue($this->tracker->isFindingAbsolved($sin->fingerprint));

        $remaining = array_map(static fn (Finding $f): string => $f->fingerprint, $this->findings());
        $this->assertNotContains($sin->fingerprint, $remaining, 'A reported sin must not still surface.');
    }

    public function test_manual_absolve_still_refuses_an_unreported_sin(): void
    {
        // The guardrail: a sin you simply do not want to fix stays refused; the
        // message points at `report` as the only legitimate escape.
        $sin = $this->findingOfKind('sin');

        $result = (new Absolver($this->manager, $this->registry, $this->tracker))
            ->absolve($sin->fingerprint, 'I do not want to fix it');

        $this->assertSame(Absolver::STATUS_ERROR, $result['status']);
        $this->assertStringContainsString('must be FIXED', $result['message']);
        $this->assertStringContainsString('report', $result['message']);
    }

    public function test_requires_a_reason(): void
    {
        $warning = $this->findingOfKind('warning');

        $result = (new Absolver($this->manager, $this->registry, $this->tracker))
            ->absolve($warning->fingerprint, '   ');

        $this->assertSame(Absolver::STATUS_ERROR, $result['status']);
        $this->assertStringContainsString('reason is required', $result['message']);
    }

    public function test_unknown_fingerprint_is_rejected(): void
    {
        $result = (new Absolver($this->manager, $this->registry, $this->tracker))
            ->absolve('deadbeefdeadbeef', 'whatever');

        $this->assertSame(Absolver::STATUS_ERROR, $result['status']);
        $this->assertStringContainsString('No live finding', $result['message']);
    }

    public function test_batch_warnings_hard_refuses_when_a_sin_is_in_scope(): void
    {
        // The fixture has a warning AND a sin. --warnings must refuse outright
        // and absolve NOTHING — sins are imperative.
        $warning = $this->findingOfKind('warning');

        $result = (new Absolver($this->manager, $this->registry, $this->tracker))
            ->absolveWarnings('accepted for now', null);

        $this->assertSame(Absolver::STATUS_ERROR, $result['status']);
        $this->assertStringContainsString('sin(s) in scope', $result['message']);
        $this->assertStringContainsString('touched NOTHING', $result['message']);
        $this->assertFalse($this->tracker->isFindingAbsolved($warning->fingerprint), 'No warning may be absolved when a sin is in scope.');
    }

    public function test_batch_warnings_prophet_filter_narrows_the_batch(): void
    {
        $registry = new ProphetRegistry();
        $registry->registerMany('warns', [OptionDisciplineProphet::class]);
        $registry->setScrollConfig('warns', [
            'path' => $this->dir,
            'extensions' => ['php'],
            'exclude' => [],
            'prophets' => [OptionDisciplineProphet::class],
        ]);
        $manager = new ScrollManager($registry, new GenericFileScanner());
        $absolver = new Absolver($manager, $registry, $this->tracker);

        // A non-matching prophet name absolves nothing.
        $miss = $absolver->absolveWarnings('reason', null, 'NoSuchProphet');
        $this->assertStringContainsString('No admonitions in scope', $miss['message']);

        // The matching prophet absolves its warning.
        $hit = $absolver->absolveWarnings('reason', null, 'OptionDiscipline');
        $this->assertSame(Absolver::STATUS_OK, $hit['status']);
        $this->assertStringContainsString('1 warning', $hit['message']);
    }

    public function test_parse_locator_handles_line_and_range(): void
    {
        $this->assertSame(['path' => 'src/Foo.php', 'from' => 32, 'to' => 32], Absolver::parseLocator('src/Foo.php:32'));
        $this->assertSame(['path' => 'src/Foo.php', 'from' => 10, 'to' => 20], Absolver::parseLocator('src/Foo.php:10-20'));
        $this->assertSame(['path' => 'src/Foo.php', 'from' => 10, 'to' => 20], Absolver::parseLocator('src/Foo.php:20-10'));
        $this->assertNull(Absolver::parseLocator('src/Foo.php'));
        $this->assertNull(Absolver::parseLocator('src/Foo.php:abc'));
    }

    public function test_findings_at_resolves_a_locator_to_its_finding(): void
    {
        $sin = $this->findingOfKind('sin');
        $absolver = new Absolver($this->manager, $this->registry, $this->tracker);

        $matches = $absolver->findingsAt($sin->relativePath, (int) $sin->line, (int) $sin->line, null);

        $fingerprints = array_map(static fn (Finding $f): string => $f->fingerprint, $matches);
        $this->assertContains($sin->fingerprint, $fingerprints);
    }

    public function test_findings_at_filters_by_prophet(): void
    {
        $warning = $this->findingOfKind('warning');
        $absolver = new Absolver($this->manager, $this->registry, $this->tracker);

        // The warning's own prophet matches; an unrelated name does not.
        $this->assertNotEmpty($absolver->findingsAt($warning->relativePath, (int) $warning->line, (int) $warning->line, 'OptionDiscipline'));
        $this->assertEmpty($absolver->findingsAt($warning->relativePath, (int) $warning->line, (int) $warning->line, 'NoSuchProphet'));
    }

    public function test_findings_at_returns_nothing_off_line(): void
    {
        $sin = $this->findingOfKind('sin');
        $absolver = new Absolver($this->manager, $this->registry, $this->tracker);

        $this->assertEmpty($absolver->findingsAt($sin->relativePath, 9999, 9999, null));
    }

    public function test_at_absolution_is_line_shift_stable_and_self_heals(): void
    {
        // --at stores NO line number: it resolves to the content-based
        // fingerprint, so the absolution survives a pure line shift (unchanged
        // code) and only goes stale when the flagged code itself changes.
        $warning = $this->findingOfKind('warning');
        $absolver = new Absolver($this->manager, $this->registry, $this->tracker);

        $resolved = $absolver->findingsAt($warning->relativePath, (int) $warning->line, (int) $warning->line, null);
        $fingerprint = $resolved[0]->fingerprint;
        $absolver->absolve($fingerprint, 'internal helper, callers are local');
        $this->assertTrue($this->tracker->isFindingAbsolved($fingerprint));

        // Shift every line down (blank lines after <?php) without touching the
        // flagged code — the absolution must survive (no stale line stored).
        $path = $this->dir . '/ServiceController.php';
        file_put_contents($path, str_replace("<?php\n", "<?php\n\n\n\n", (string) file_get_contents($path)));

        $warningStillHidden = true;

        foreach ($this->findings() as $finding) {
            if ($finding->kind === 'warning' && $finding->prophetShort === $warning->prophetShort) {
                $warningStillHidden = false;
            }
        }

        $this->assertTrue($warningStillHidden, 'A pure line shift must not re-surface the absolved finding.');
    }

    public function test_absolve_all_baselines_warnings_but_not_sins(): void
    {
        $absolver = new Absolver($this->manager, $this->registry, $this->tracker);

        $result = $absolver->absolveAll('baseline backlog');

        $this->assertGreaterThanOrEqual(1, $result['absolved']);
        $this->assertGreaterThanOrEqual(1, $result['blocking_sins']);

        // The warning is now absolved; the sin still surfaces.
        $remaining = $this->findings();
        $this->assertNotEmpty($remaining, 'The sin should still be reported.');
        foreach ($remaining as $finding) {
            $this->assertSame('sin', $finding->kind, 'Only sins should remain after baselining.');
        }
    }
}
