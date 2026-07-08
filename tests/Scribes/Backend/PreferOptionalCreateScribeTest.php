<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Scribes\Backend;

use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\Backend\Spatie\PreferOptionalCreateDetector;
use JesseGall\CodeCommandments\Scribes\Backend\PreferOptionalCreateScribe;
use JesseGall\CodeCommandments\Scribes\RepentScribe;

final class PreferOptionalCreateScribeTest extends ScribeTestCase
{
    protected function detector(): Detector
    {
        return new PreferOptionalCreateDetector();
    }

    protected function scribe(): RepentScribe
    {
        return new PreferOptionalCreateScribe();
    }

    public function test_rewrites_runtime_new_optional_to_the_factory(): void
    {
        $php = <<<'PHP'
        <?php

        namespace Spatie\LaravelData { class Optional { public static function create(): Optional { return new self(); } } }

        namespace App {
            use Spatie\LaravelData\Optional;

            class Maker
            {
                public function make(bool $absent): mixed
                {
                    if ($absent) {
                        return new Optional();
                    }

                    return 'x';
                }
            }
        }
        PHP;

        $fixed = $this->fixStable($php);

        $this->assertStringContainsString('return Optional::create();', $fixed);
        $this->assertStringNotContainsString('new Optional()', $fixed);
    }

    public function test_leaves_a_parameter_default_alone(): void
    {
        $php = <<<'PHP'
        <?php

        namespace Spatie\LaravelData { class Optional {} class Data {} }

        namespace App {
            use Spatie\LaravelData\Data;
            use Spatie\LaravelData\Optional;

            final class Page extends Data
            {
                public function __construct(public readonly Optional $at = new Optional()) {}
            }
        }
        PHP;

        $this->assertFalse($this->rewrote($php));
    }
}
