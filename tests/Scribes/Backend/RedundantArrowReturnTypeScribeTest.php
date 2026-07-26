<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Scribes\Backend;

use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\Backend\RedundantArrowReturnTypeDetector;
use JesseGall\CodeCommandments\Scribes\Backend\RedundantArrowReturnTypeScribe;
use JesseGall\CodeCommandments\Scribes\RepentScribe;

final class RedundantArrowReturnTypeScribeTest extends ScribeTestCase
{
    protected function detector(): Detector
    {
        return new RedundantArrowReturnTypeDetector();
    }

    protected function scribe(): RepentScribe
    {
        return new RedundantArrowReturnTypeScribe();
    }

    public function test_takes_the_type_off_and_leaves_the_arrow_intact(): void
    {
        $php = <<<'PHP'
        <?php

        namespace App;

        final class Panel
        {
            private string $name = 'x';

            public function all(): array
            {
                return [
                    fn (): string => $this->name,
                ];
            }
        }
        PHP;

        $expected = <<<'PHP'
        <?php

        namespace App;

        final class Panel
        {
            private string $name = 'x';

            public function all(): array
            {
                return [
                    fn () => $this->name,
                ];
            }
        }
        PHP;

        $this->assertSame($expected, $this->fixStable($php));
    }

    public function test_takes_a_class_type_off_a_construction(): void
    {
        $php = <<<'PHP'
        <?php

        namespace App;

        final class Money {}

        final class Wallet
        {
            public function make(): callable
            {
                return fn (): Money => new Money();
            }
        }
        PHP;

        $fixed = $this->fixStable($php);

        $this->assertStringContainsString('return fn () => new Money();', $fixed);
    }

    public function test_does_not_overshoot_onto_a_type_that_is_doing_work(): void
    {
        // Ambiguous expression, and a widening — both types stay.
        $php = <<<'PHP'
        <?php

        namespace App;

        final class Panel
        {
            private string $name = 'x';

            public function all(): array
            {
                return [
                    fn (): string => $this->name === '' ? 'a' : 'b',
                    fn (): ?string => $this->name,
                ];
            }
        }
        PHP;

        $this->assertFalse($this->rewrote($php));
        $this->assertSame($php, $this->fix($php));
    }

    public function test_is_idempotent(): void
    {
        $php = <<<'PHP'
        <?php

        namespace App;

        final class Panel
        {
            private int $count = 0;

            public function all(): array
            {
                return [
                    fn (): int => $this->count,
                    fn (): int => 42,
                ];
            }
        }
        PHP;

        $fixed = $this->fixStable($php);

        $this->assertSame([], $this->findings($fixed), 'the sin no longer fires');
        $this->assertSame($fixed, $this->fix($fixed), 'a second pass changes nothing');
        $this->assertStringContainsString('fn () => $this->count,', $fixed);
    }
}
