<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Feature;

use JesseGall\CodeCommandments\Contracts\Commandment;
use JesseGall\CodeCommandments\Contracts\NeedsCodebaseIndex;
use JesseGall\CodeCommandments\Prophets\Backend\NoNullCoalesceToNullProphet;
use JesseGall\CodeCommandments\Prophets\Backend\NoOptionToNullProphet;
use JesseGall\CodeCommandments\Prophets\Backend\NoSwallowedNotFoundProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferEmptyOverNullProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferNullObjectDefaultsProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferOptionOverNullProphet;
use JesseGall\CodeCommandments\Prophets\Backend\PreferTotalOverNullableProphet;
use JesseGall\CodeCommandments\Prophets\Backend\RegistryNamingHonestyProphet;
use JesseGall\CodeCommandments\Prophets\Backend\RegistryReturnContractProphet;
use JesseGall\CodeCommandments\Prophets\Backend\ThrowOnUnhandledCaseProphet;
use JesseGall\CodeCommandments\Results\Finding;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use JesseGall\CodeCommandments\Support\FindingQueue;
use JesseGall\CodeCommandments\Support\RootCauseResolver;
use PHPUnit\Framework\TestCase;

/**
 * The executable specification: every example's initial/ produces the expected
 * findings (cause leads, symptom deferred in a full run / hinted under filtering;
 * genuine absence left clean) and every final/ is clean. The example READMEs are
 * the human-readable spec for these assertions.
 */
class RootCausePrecedenceTest extends TestCase
{
    private const EXAMPLES = __DIR__ . '/../../invariant-root-cause/examples';

    private const UPRR = __DIR__ . '/../../invariant-root-cause/unpack-port-resolver-refactor';

    // ── 01 — enum default => null ────────────────────────────────────────────

    public function test_example_01_initial_cause_leads_symptom(): void
    {
        $f = $this->familyFindings(self::EXAMPLES . '/example-01-enum-dispatch/initial');

        // ROOT CAUSE and SYMPTOM both fire on the enum.
        $this->assertHasFinding($f, 'ThrowOnUnhandledCaseProphet', 'OrderStatus.php');
        $this->assertHasFinding($f, 'PreferOptionOverNullProphet', 'OrderStatus.php');

        // Full run: the symptom is DEFERRED (the cause supersedes it in-region).
        $ordered = $this->orderedSymbols($this->familyFindingObjects(self::EXAMPLES . '/example-01-enum-dispatch/initial'));
        $this->assertContains('ThrowOnUnhandledCaseProphet', $ordered);
        $this->assertNotContains('PreferOptionOverNullProphet', $ordered, 'symptom should be deferred under a full run');
    }

    public function test_example_01_filtered_symptom_gets_root_cause_hint(): void
    {
        $dir = self::EXAMPLES . '/example-01-enum-dispatch/initial';
        $symptom = $this->firstFindingObject($dir, PreferOptionOverNullProphet::class);
        $this->assertNotNull($symptom);

        $resolver = new RootCauseResolver(fn (): null => null);
        $annotated = $resolver->annotate($symptom, [PreferOptionOverNullProphet::class => true]);

        $this->assertNotNull($annotated->rootCauseHint);
        $this->assertSame(ThrowOnUnhandledCaseProphet::class, $annotated->rootCauseHint->causeClass);
    }

    public function test_example_01_final_is_clean(): void
    {
        $this->assertCleanFamily(self::EXAMPLES . '/example-01-enum-dispatch/final');
    }

    // ── 02 — registry returns ?T ─────────────────────────────────────────────

    public function test_example_02_registry_cause_and_auto_fixable_symptom_fire(): void
    {
        $f = $this->familyFindings(self::EXAMPLES . '/example-02-registry-contract/initial');

        $this->assertHasFinding($f, 'RegistryReturnContractProphet', 'PaymentGatewayRegistry.php');
        $this->assertHasFinding($f, 'NoNullCoalesceToNullProphet', 'PaymentGatewayRegistry.php');
    }

    public function test_example_02_final_is_clean(): void
    {
        $this->assertCleanFamily(self::EXAMPLES . '/example-02-registry-contract/final');
    }

    // ── 03 — private nullable helper → Null Object ───────────────────────────

    public function test_example_03_null_object_symptom_fires(): void
    {
        $f = $this->familyFindings(self::EXAMPLES . '/example-03-null-object/initial');
        $this->assertHasFinding($f, 'PreferNullObjectDefaultsProphet', 'Cart.php');
    }

    public function test_example_03_final_is_clean(): void
    {
        $this->assertCleanFamily(self::EXAMPLES . '/example-03-null-object/final');
    }

    // ── 04 — swallowed not-found ─────────────────────────────────────────────

    public function test_example_04_swallowed_not_found_fires(): void
    {
        $f = $this->familyFindings(self::EXAMPLES . '/example-04-swallowed-notfound/initial');
        $this->assertHasFinding($f, 'NoSwallowedNotFoundProphet', 'UserProfileService.php');
    }

    public function test_example_04_final_is_clean(): void
    {
        $this->assertCleanFamily(self::EXAMPLES . '/example-04-swallowed-notfound/final');
    }

    // ── 05 — invariant vs genuine, the negative test ─────────────────────────

    public function test_example_05_invariant_getById_is_flagged(): void
    {
        $f = $this->familyFindings(self::EXAMPLES . '/example-05-genuine-vs-invariant/initial');
        // getById (FK invariant) → registry contract warning.
        $this->assertTrue(
            $this->hasSymbol($f, 'registry-return-shape:getById'),
            'getById should be flagged as an invariant registry getter',
        );
    }

    public function test_example_05_genuine_findByEmail_fires_with_no_hint(): void
    {
        $dir = self::EXAMPLES . '/example-05-genuine-vs-invariant/initial';

        // findByEmail is GENUINE absence → PreferOptionOverNull fires...
        $symptom = $this->firstFindingObject($dir, PreferOptionOverNullProphet::class, 'findByEmail');
        $this->assertNotNull($symptom, 'PreferOptionOverNull should fire on findByEmail');

        // ...and the trigger runs, matches no invariant cause, leaves NO hint
        // (confirmed genuine absence — the symptom is the right fix).
        $index = CodebaseIndex::build($this->phpFiles($dir));
        $resolver = new RootCauseResolver(fn (): CodebaseIndex => $index);
        $annotated = $resolver->annotate($symptom, [PreferOptionOverNullProphet::class => true]);

        $this->assertNull($annotated->rootCauseHint, 'genuine absence must not get a root-cause hint');
        $this->assertTrue($annotated->rootCauseChecked, 'genuine absence should be marked checked');
    }

    public function test_example_05_final_is_clean(): void
    {
        $this->assertCleanFamily(self::EXAMPLES . '/example-05-genuine-vs-invariant/final');
    }

    // ── 06 — multi-file subsystem ────────────────────────────────────────────

    public function test_example_06_each_cause_fires(): void
    {
        $f = $this->familyFindings(self::EXAMPLES . '/example-06-notifications-subsystem/initial');

        $this->assertHasFinding($f, 'ThrowOnUnhandledCaseProphet', 'Severity.php');
        $this->assertHasFinding($f, 'RegistryReturnContractProphet', 'ChannelRegistry.php');
        $this->assertHasFinding($f, 'PreferTotalOverNullableProphet', 'AlertDispatcher.php');
        $this->assertHasFinding($f, 'NoOptionToNullProphet', 'AlertDispatcher.php');
    }

    public function test_example_06_final_is_clean(): void
    {
        $this->assertCleanFamily(self::EXAMPLES . '/example-06-notifications-subsystem/final');
    }

    // ── 07 — real-world UnpackPortResolver ───────────────────────────────────

    public function test_example_07_registry_naming_advisory_fires_on_initial(): void
    {
        $dir = self::UPRR . '/initial';
        $index = CodebaseIndex::build($this->phpFiles($dir));
        $prophet = new RegistryNamingHonestyProphet();

        $judgment = $prophet->judge($dir . '/UnpackPortResolver.php', file_get_contents($dir . '/UnpackPortResolver.php'));
        $this->assertCount(1, $judgment->warnings);
        $this->assertSame('registry-naming:UnpackPortResolver', $judgment->warnings[0]->symbol);

        // The absence family must NOT misfire on the genuine Option / getOr-default
        // / tap code — this is the "keep, don't rewrite" case.
        $this->assertCleanFamily($dir);
    }

    public function test_example_07_final_is_clean(): void
    {
        // final UnpackerRegistry is named *Registry → naming advisory silent too.
        $dir = self::UPRR . '/final';
        $this->assertCleanFamily($dir);

        $naming = new RegistryNamingHonestyProphet();
        $judgment = $naming->judge($dir . '/UnpackerRegistry.php', file_get_contents($dir . '/UnpackerRegistry.php'));
        $this->assertTrue($judgment->isRighteous());
    }

    // ── harness ──────────────────────────────────────────────────────────────

    /**
     * @return list<Commandment>
     */
    private function family(CodebaseIndex $index): array
    {
        $option = ['option_class' => 'Support\\Option'];

        $prophets = [
            new ThrowOnUnhandledCaseProphet(),
            new PreferTotalOverNullableProphet(),
            new RegistryReturnContractProphet(),
            new NoSwallowedNotFoundProphet(),
            (new PreferOptionOverNullProphet())->configure($option + ['min_callers' => 1]),
            new PreferEmptyOverNullProphet(),
            new PreferNullObjectDefaultsProphet(),
            (new NoOptionToNullProphet())->configure($option),
            new NoNullCoalesceToNullProphet(),
        ];

        foreach ($prophets as $prophet) {
            if ($prophet instanceof NeedsCodebaseIndex) {
                $prophet->setCodebaseIndex($index);
            }
        }

        return $prophets;
    }

    /**
     * @return list<array{prophet: string, file: string, line: ?int, kind: string, symbol: ?string}>
     */
    private function familyFindings(string $dir): array
    {
        $files = $this->phpFiles($dir);
        $index = CodebaseIndex::build($files);
        $prophets = $this->family($index);
        $out = [];

        foreach ($files as $file) {
            $content = file_get_contents($file);

            foreach ($prophets as $prophet) {
                $judgment = $prophet->judge($file, $content);
                $short = class_basename($prophet);

                foreach ($judgment->sins as $sin) {
                    $out[] = ['prophet' => $short, 'file' => basename($file), 'line' => $sin->line, 'kind' => 'sin', 'symbol' => $sin->symbol];
                }

                foreach ($judgment->warnings as $warning) {
                    $out[] = ['prophet' => $short, 'file' => basename($file), 'line' => $warning->line, 'kind' => 'warning', 'symbol' => $warning->symbol];
                }
            }
        }

        return $out;
    }

    /**
     * @return list<Finding>
     */
    private function familyFindingObjects(string $dir, ?string $onlyProphet = null): array
    {
        $files = $this->phpFiles($dir);
        $index = CodebaseIndex::build($files);
        $prophets = $this->family($index);
        $findings = [];

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $relative = basename($file);

            foreach ($prophets as $prophet) {
                if ($onlyProphet !== null && ! $prophet instanceof $onlyProphet) {
                    continue;
                }

                $class = get_class($prophet);
                $judgment = $prophet->judge($file, $content);

                foreach (['sin' => $judgment->sins, 'warning' => $judgment->warnings] as $kind => $list) {
                    foreach ($list as $item) {
                        $findings[] = new Finding(
                            prophetClass: $class,
                            prophetShort: class_basename($prophet),
                            filePath: $file,
                            relativePath: $relative,
                            kind: $kind,
                            line: $item->line,
                            message: $item->message,
                            snippet: $item->snippet,
                            suggestion: null,
                            symbol: $item->symbol,
                            advisory: $prophet->advisory(),
                            tier: $prophet->tier(),
                            supersedes: $prophet->supersedes(),
                            fingerprint: $class . ':' . $relative . ':' . ($item->symbol ?? ''),
                            autoFixable: $item->autoFixable ?? false,
                            rootCauses: $prophet->rootCauses(),
                        );
                    }
                }
            }
        }

        return $findings;
    }

    private function firstFindingObject(string $dir, string $prophetClass, ?string $symbolContains = null): ?Finding
    {
        foreach ($this->familyFindingObjects($dir, $prophetClass) as $finding) {
            if ($symbolContains === null || str_contains((string) $finding->symbol, $symbolContains) || str_contains((string) $finding->message, $symbolContains)) {
                return $finding;
            }
        }

        return null;
    }

    /**
     * @param  list<Finding>  $findings
     * @return list<string>  prophet short names that survive ordering (not deferred)
     */
    private function orderedSymbols(array $findings): array
    {
        return array_values(array_map(
            static fn (Finding $f): string => $f->prophetShort,
            FindingQueue::order($findings),
        ));
    }

    /**
     * @param  list<array{prophet: string, file: string, line: ?int, kind: string, symbol: ?string}>  $findings
     */
    private function assertHasFinding(array $findings, string $prophetShort, string $file): void
    {
        foreach ($findings as $finding) {
            if ($finding['prophet'] === $prophetShort && $finding['file'] === $file) {
                $this->assertTrue(true);

                return;
            }
        }

        $this->fail("Expected {$prophetShort} to fire on {$file}; got: " . json_encode($findings));
    }

    /**
     * @param  list<array{prophet: string, file: string, line: ?int, kind: string, symbol: ?string}>  $findings
     */
    private function hasSymbol(array $findings, string $symbol): bool
    {
        foreach ($findings as $finding) {
            if ($finding['symbol'] === $symbol) {
                return true;
            }
        }

        return false;
    }

    private function assertCleanFamily(string $dir): void
    {
        $findings = $this->familyFindings($dir);

        $this->assertSame([], $findings, "Expected no invariant/absence-family findings in {$dir}, got: " . json_encode($findings));
    }

    /**
     * @return list<string>
     */
    private function phpFiles(string $dir): array
    {
        return array_values(array_filter((array) glob($dir . '/*.php')));
    }
}
