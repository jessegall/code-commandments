<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support;

use JesseGall\CodeCommandments\Prophets\Backend\PreferOptionOverNullProphet;
use JesseGall\CodeCommandments\Prophets\Backend\ThrowOnUnhandledCaseProphet;
use JesseGall\CodeCommandments\Results\Finding;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Support\RootCauseResolver;
use PHPUnit\Framework\TestCase;

class RootCauseResolverTest extends TestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }

        $this->tempFiles = [];

        parent::tearDown();
    }

    public function test_filtered_run_annotates_symptom_with_root_cause_hint(): void
    {
        // An enum whose dispatch returns null on the default arm — the real
        // root cause (ThrowOnUnhandledCase fires) under a symptom that did not
        // bring the cause prophet along (filtered to the symptom only).
        $file = $this->writeTemp(<<<'PHP'
<?php
namespace Demo;
enum Status: string {
    case Open = 'open';
    case Paid = 'paid';

    public function priority(): ?int {
        return match ($this) {
            self::Open => 1,
            self::Paid => 2,
            default => null,
        };
    }
}
PHP);

        $resolver = new RootCauseResolver(fn (): null => null);
        $finding = $this->symptomFinding($file, $this->lineContaining($file, 'function priority'));

        $result = $resolver->annotate($finding, [PreferOptionOverNullProphet::class => true]);

        $this->assertNotNull($result->rootCauseHint);
        $this->assertSame(ThrowOnUnhandledCaseProphet::class, $result->rootCauseHint->causeClass);
        $this->assertSame('ThrowOnUnhandledCaseProphet', $result->rootCauseHint->causeShort);
        $this->assertNotSame('', $result->rootCauseHint->reason);
        $this->assertTrue($result->rootCauseChecked);
    }

    public function test_full_run_leaves_finding_unannotated(): void
    {
        $file = $this->writeTemp(<<<'PHP'
<?php
namespace Demo;
enum Status: string {
    case Open = 'open';
    case Paid = 'paid';

    public function priority(): ?int {
        return match ($this) {
            self::Open => 1,
            self::Paid => 2,
            default => null,
        };
    }
}
PHP);

        $resolver = new RootCauseResolver(fn (): null => null);
        $finding = $this->symptomFinding($file, $this->lineContaining($file, 'function priority'));

        // Every cause is active (a full, unfiltered run) → supersedes deferral
        // handles ordering; the resolver must be a no-op.
        $active = [PreferOptionOverNullProphet::class => true];
        foreach ($finding->rootCauses as $cause) {
            $active[$cause] = true;
        }

        $result = $resolver->annotate($finding, $active);

        $this->assertSame($finding, $result);
        $this->assertNull($result->rootCauseHint);
        $this->assertFalse($result->rootCauseChecked);
    }

    public function test_genuine_absence_is_checked_with_no_hint(): void
    {
        // A plain class deciding null with no invariant cause anywhere — the
        // negative case: the symptom IS the right fix, so it is marked checked.
        $file = $this->writeTemp(<<<'PHP'
<?php
namespace Demo;
class Search {
    public function symptom(int $n): ?int {
        if ($n > 0) {
            return $n;
        }
        return null;
    }
}
PHP);

        $resolver = new RootCauseResolver(fn (): null => null);
        $finding = $this->symptomFinding($file, $this->lineContaining($file, 'function symptom'));

        $result = $resolver->annotate($finding, [PreferOptionOverNullProphet::class => true]);

        $this->assertNull($result->rootCauseHint);
        $this->assertTrue($result->rootCauseChecked);
    }

    public function test_finding_without_root_causes_is_returned_unchanged(): void
    {
        $resolver = new RootCauseResolver(fn (): null => null);
        $finding = $this->symptomFinding('/nonexistent.php', 1, rootCauses: []);

        $this->assertSame($finding, $resolver->annotate($finding, []));
    }

    /**
     * @param  list<string>|null  $rootCauses
     */
    private function symptomFinding(string $filePath, int $line, ?array $rootCauses = null): Finding
    {
        return new Finding(
            prophetClass: PreferOptionOverNullProphet::class,
            prophetShort: 'PreferOptionOverNullProphet',
            filePath: $filePath,
            relativePath: basename($filePath),
            kind: 'warning',
            line: $line,
            message: 'decides nothingness with null',
            snippet: null,
            suggestion: 'wrap in Option',
            symbol: 'symptom',
            advisory: null,
            tier: Tier::Structural,
            supersedes: [],
            fingerprint: 'fp',
            autoFixable: false,
            rootCauses: $rootCauses ?? (new PreferOptionOverNullProphet())->rootCauses(),
        );
    }

    private function writeTemp(string $content): string
    {
        $file = tempnam(sys_get_temp_dir(), 'rcr') . '.php';
        file_put_contents($file, $content);
        $this->tempFiles[] = $file;

        return $file;
    }

    private function lineContaining(string $file, string $needle): int
    {
        foreach (file($file) ?: [] as $i => $line) {
            if (str_contains($line, $needle)) {
                return $i + 1;
            }
        }

        return 1;
    }
}
