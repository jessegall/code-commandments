<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Scribes\Backend;

use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\Backend\Spatie\RedundantNativeCastDetector;
use JesseGall\CodeCommandments\Scribes\Backend\RedundantNativeCastScribe;
use JesseGall\CodeCommandments\Scribes\RepentScribe;

/**
 * The redundant-native-cast fix unwraps `Enum::from($x)` to `$x`, letting the property's built-in enum cast
 * build it. It fixes, does not overshoot a `tryFrom` sibling, and is idempotent.
 */
final class RedundantNativeCastScribeTest extends ScribeTestCase
{
    protected function detector(): Detector
    {
        return new RedundantNativeCastDetector();
    }

    protected function scribe(): RepentScribe
    {
        return new RedundantNativeCastScribe();
    }

    private const HEADER = <<<'PHP'
        <?php
        namespace App;
        use Spatie\LaravelData\Data;
        enum Status: string { case A = 'a'; }
        PHP;

    public function test_unwraps_the_enum_from_to_the_raw_scalar(): void
    {
        $fixed = $this->fixStable(self::HEADER . "\n" . <<<'PHP'
            final class Payload extends Data {
                public function __construct(public readonly Status $status) {}
            }
            final class Builder {
                public function make(array $raw): Payload {
                    return Payload::from(['status' => Status::from($raw['status'])]);
                }
            }
            PHP);

        $this->assertStringContainsString("'status' => \$raw['status']", $fixed);
        $this->assertStringNotContainsString('Status::from(', $fixed);
    }

    public function test_does_not_overshoot_a_try_from(): void
    {
        $php = self::HEADER . "\n" . <<<'PHP'
            final class Payload extends Data {
                public function __construct(public readonly ?Status $status) {}
            }
            final class Builder {
                public function make(array $raw): Payload {
                    return Payload::from(['status' => Status::tryFrom($raw['status'])]);
                }
            }
            PHP;

        $this->assertFalse($this->rewrote($php), 'a tolerant tryFrom must be left untouched');
    }
}
