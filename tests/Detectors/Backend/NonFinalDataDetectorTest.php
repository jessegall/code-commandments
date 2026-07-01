<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\Spatie\NonFinalDataDetector;
use PHPUnit\Framework\TestCase;

final class NonFinalDataDetectorTest extends TestCase
{
    public function test_flags_a_non_final_data_class_only(): void
    {
        $code = <<<'PHP'
        <?php
        namespace Spatie\LaravelData { class Data {} }
        namespace App {
            use Spatie\LaravelData\Data;
            class OpenData extends Data {}
            final class SealedData extends Data {}
            class NotData {}
        }
        PHP;

        $hits = (new NonFinalDataDetector)->find(Codebase::fromString($code));
        $names = array_map(static fn ($m): string => $m->enclosingClassName() ?? '?', $hits);

        $this->assertSame(['App\\OpenData'], $names);
    }

    public function test_leaves_a_morphable_base_that_is_extended_alone(): void
    {
        $code = <<<'PHP'
        <?php
        namespace Spatie\LaravelData { class Data {} }
        namespace App {
            use Spatie\LaravelData\Data;
            // a base in a morphable hierarchy: can't be final (fatal), so not a sin
            class SelectSocket extends Data {}
            abstract class PickerSocket extends SelectSocket {}
            final class ResourcePickerSocket extends PickerSocket {}
            // a plain non-final leaf with no subclasses IS the sin
            class LooseLeaf extends Data {}
        }
        PHP;

        $hits = (new NonFinalDataDetector)->find(Codebase::fromString($code));
        $names = array_map(static fn ($m): string => $m->enclosingClassName() ?? '?', $hits);

        $this->assertSame(['App\\LooseLeaf'], $names);
    }
}
