<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\AllNullableDataDetector;
use PHPUnit\Framework\TestCase;

final class AllNullableDataDetectorTest extends TestCase
{
    public function test_flags_an_all_optional_data_class_only(): void
    {
        $code = <<<'PHP'
        <?php
        namespace Spatie\LaravelData { class Data {} }
        namespace App {
            use Spatie\LaravelData\Data;
            class RawPayload extends Data {
                public function __construct(
                    public readonly ?string $type = null,
                    public readonly int | null $amount = null,
                ) {}
            }
            class Honest extends Data {
                public function __construct(
                    public readonly int $id,
                    public readonly ?string $note = null,
                ) {}
            }
            class NotData {
                public function __construct(public readonly ?string $x = null) {}
            }
        }
        PHP;

        $hits = (new AllNullableDataDetector)->find(Codebase::fromString($code));
        $names = array_map(static fn ($m): string => $m->enclosingClassName() ?? '?', $hits);

        $this->assertSame(['App\\RawPayload'], $names);
    }
}
