<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support;

use Illuminate\Filesystem\Filesystem;
use JesseGall\CodeCommandments\Prophets\Backend\NoRawRequestProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferOptionOverNullProphet;
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
            public function a(): void { $this->findRef([]); }
            public function b(): void { $this->findRef([]); }
            public function c(): void { $this->findRef([]); }
            public function store(Request $request): mixed { return $request->input('name'); }
        }
        PHP);

        $this->tablet = $this->dir . '/.commandments/confessions.json';

        $this->registry = new ProphetRegistry();
        $this->registry->registerMany('backend', [
            PreferOptionOverNullProphet::class,
            NoRawRequestProphet::class,
        ]);
        $this->registry->setScrollConfig('backend', [
            'path' => $this->dir,
            'extensions' => ['php'],
            'exclude' => [],
            'prophets' => [PreferOptionOverNullProphet::class, NoRawRequestProphet::class],
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
            ->absolveWarnings('accepted for now', null, false);

        $this->assertSame(Absolver::STATUS_ERROR, $result['status']);
        $this->assertStringContainsString('sin(s) in scope', $result['message']);
        $this->assertStringContainsString('touched NOTHING', $result['message']);
        $this->assertFalse($this->tracker->isFindingAbsolved($warning->fingerprint), 'No warning may be absolved when a sin is in scope.');
    }

    public function test_batch_warnings_until_push_absolves_stickily_when_no_sin(): void
    {
        // Capture the warning fingerprint before it gets absolved away.
        $warningFingerprint = $this->findingOfKind('warning')->fingerprint;

        // A registry with only the warning prophet — no sin in scope.
        $registry = new ProphetRegistry();
        $registry->registerMany('warns', [PreferOptionOverNullProphet::class]);
        $registry->setScrollConfig('warns', [
            'path' => $this->dir,
            'extensions' => ['php'],
            'exclude' => [],
            'prophets' => [PreferOptionOverNullProphet::class],
        ]);
        $manager = new ScrollManager($registry, new GenericFileScanner());

        $result = (new Absolver($manager, $registry, $this->tracker))
            ->absolveWarnings('reasoned LEAVE for this grind', null, true);

        $this->assertSame(Absolver::STATUS_OK, $result['status']);
        $this->assertStringContainsString('until push', $result['message']);

        // Sticky: it survives the post-commit reset.
        $this->tracker->clearFindingAbsolutions();
        $this->assertTrue($this->tracker->isFindingAbsolved($warningFingerprint));

        // ...and clears at push.
        $this->tracker->clearUntilPushAbsolutions();
        $this->assertFalse($this->tracker->isFindingAbsolved($warningFingerprint));
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
