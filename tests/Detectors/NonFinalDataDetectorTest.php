<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\NonFinalDataDetector;
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
}
