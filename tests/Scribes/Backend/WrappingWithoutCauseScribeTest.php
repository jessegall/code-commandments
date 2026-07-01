<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Scribes\Backend;

use JesseGall\CodeCommandments\Detectors\Backend\WrappingWithoutCauseDetector;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Scribes\Backend\WrappingWithoutCauseScribe;
use JesseGall\CodeCommandments\Scribes\RepentScribe;

final class WrappingWithoutCauseScribeTest extends ScribeTestCase
{
    protected function detector(): Detector
    {
        return new WrappingWithoutCauseDetector();
    }

    protected function scribe(): RepentScribe
    {
        return new WrappingWithoutCauseScribe();
    }

    public function test_passes_the_caught_exception_as_the_previous_cause(): void
    {
        $php = <<<'PHP'
        <?php

        class Loader
        {
            public function load(): void
            {
                try {
                    $this->risky();
                } catch (\RuntimeException $e) {
                    throw new LoadFailed('could not load');
                }
            }
        }
        PHP;

        $fixed = $this->fixStable($php);

        $this->assertStringContainsString("throw new LoadFailed('could not load', previous: \$e);", $fixed);
    }

    public function test_adds_the_cause_even_with_no_existing_arguments(): void
    {
        $php = <<<'PHP'
        <?php

        class Loader
        {
            public function load(): void
            {
                try {
                    $this->risky();
                } catch (\Throwable $error) {
                    throw new LoadFailed();
                }
            }
        }
        PHP;

        $fixed = $this->fixStable($php);

        $this->assertStringContainsString('throw new LoadFailed(previous: $error);', $fixed);
    }

    public function test_does_not_overshoot_a_throw_that_already_forwards_the_cause(): void
    {
        // The cause is already passed → not flagged; a bare throw outside a catch → not flagged.
        $php = <<<'PHP'
        <?php

        class Loader
        {
            public function load(): void
            {
                try {
                    $this->risky();
                } catch (\RuntimeException $e) {
                    throw new LoadFailed('nope', previous: $e);
                }
            }

            public function guard(int $n): void
            {
                if ($n < 0) {
                    throw new InvalidInput('negative');
                }
            }
        }
        PHP;

        $this->assertSame([], $this->findings($php));
        $this->assertSame($php, $this->fix($php));
    }
}
