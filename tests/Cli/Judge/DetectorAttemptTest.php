<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Judge;

use JesseGall\CodeCommandments\Cli\Judge\DetectorAttempt;
use PHPUnit\Framework\TestCase;

/**
 * A rule that breaks costs its own findings and nothing else — and says so. A run of a hundred
 * detectors must not end because one of them met a shape it could not read.
 */
final class DetectorAttemptTest extends TestCase
{
    public function test_a_detector_that_works_hands_back_what_it_found(): void
    {
        $attempt = DetectorAttempt::of('Fine', false, static fn (): array => ['a', 'b']);

        $this->assertSame(['a', 'b'], $attempt->found);
        $this->assertNull($attempt->skipped, 'a rule that ran is not a skipped one');
    }

    public function test_a_detector_that_throws_costs_only_itself(): void
    {
        $attempt = DetectorAttempt::of(
            'Broken',
            false,
            static fn (): array => throw new \RuntimeException('met a shape it could not read'),
        );

        $this->assertSame([], $attempt->found, 'the run continues, with nothing from that rule');
    }

    public function test_the_skip_travels_back_with_the_findings(): void
    {
        // Empty findings and a skipped rule are indistinguishable to a caller that only receives
        // the findings — so the rule that could not run comes back NAMED, and the run can say so.
        $attempt = DetectorAttempt::of(
            'Broken',
            false,
            static fn (): array => throw new \RuntimeException('met a shape it could not read'),
        );

        $this->assertSame('Broken', $attempt->skipped);
    }

    public function test_the_failure_is_REPORTED_not_swallowed(): void
    {
        // A silent skip reads as "that rule found nothing", which is the one wrong conclusion to
        // leave a reader with. STDERR cannot be swapped in-process, so this reads it for real.
        $stderr = $this->stderrOf('Broken', custom: false);

        $this->assertStringContainsString('Broken', $stderr);
        $this->assertStringContainsString('met a shape it could not read', $stderr);
        $this->assertStringContainsString('everything else still ran', $stderr);
    }

    public function test_a_projects_own_rule_is_named_as_theirs(): void
    {
        // We cannot answer for a rule we did not ship, so the report says whose it is.
        $this->assertStringContainsString('.commandments/custom/', $this->stderrOf('TheirRule', custom: true));
        $this->assertStringNotContainsString('.commandments/custom/', $this->stderrOf('OurRule', custom: false));
    }

    /**
     * What a failing attempt writes to STDERR, read from a real process.
     */
    private function stderrOf(string $detector, bool $custom): string
    {
        $autoload = dirname(__DIR__, 3) . '/vendor/autoload.php';
        $script = sprintf(
            'require %s; %s::of(%s, %s, static fn (): array => throw new \RuntimeException(%s));',
            var_export($autoload, true),
            '\\' . DetectorAttempt::class,
            var_export($detector, true),
            $custom ? 'true' : 'false',
            var_export('met a shape it could not read', true),
        );

        $process = proc_open(
            [PHP_BINARY, '-r', $script],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );

        $stderr = (string) stream_get_contents($pipes[2]);

        foreach ($pipes as $pipe) {
            fclose($pipe);
        }

        proc_close($process);

        return $stderr;
    }
}
