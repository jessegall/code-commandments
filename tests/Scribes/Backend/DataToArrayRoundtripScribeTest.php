<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Scribes\Backend;

use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\Backend\Spatie\DataToArrayRoundtripDetector;
use JesseGall\CodeCommandments\Scribes\Backend\DataToArrayRoundtripScribe;
use JesseGall\CodeCommandments\Scribes\RepentScribe;

/**
 * The round-trip fix drops the redundant `->toArray()`, leaving the `Data` object for the slot to take
 * directly. It fixes, does not overshoot a plain-array slot, and is idempotent.
 */
final class DataToArrayRoundtripScribeTest extends ScribeTestCase
{
    protected function detector(): Detector
    {
        return new DataToArrayRoundtripDetector();
    }

    protected function scribe(): RepentScribe
    {
        return new DataToArrayRoundtripScribe();
    }

    private const HEADER = <<<'PHP'
        <?php
        namespace App;
        use Spatie\LaravelData\Data;
        final class Inner extends Data { public function __construct(public readonly string $label) {} }
        PHP;

    public function test_drops_the_redundant_to_array(): void
    {
        $fixed = $this->fixStable(self::HEADER . "\n" . <<<'PHP'
            final class Outer extends Data {
                public function __construct(public readonly Inner $inner) {}
            }
            final class Builder {
                public function make(Inner $inner): Outer {
                    return Outer::from(['inner' => $inner->toArray()]);
                }
            }
            PHP);

        $this->assertStringContainsString("'inner' => \$inner", $fixed);
        $this->assertStringNotContainsString('->toArray()', $fixed);
    }

    public function test_does_not_overshoot_a_plain_array_slot(): void
    {
        $php = self::HEADER . "\n" . <<<'PHP'
            final class Outer extends Data {
                public function __construct(public readonly array $inner) {}
            }
            final class Builder {
                public function make(Inner $inner): Outer {
                    return Outer::from(['inner' => $inner->toArray()]);
                }
            }
            PHP;

        $this->assertFalse($this->rewrote($php), 'a plain-array slot genuinely wants the array');
    }
}
