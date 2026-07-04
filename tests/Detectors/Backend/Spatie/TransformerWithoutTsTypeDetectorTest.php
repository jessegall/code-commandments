<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\Spatie\TransformerWithoutTsTypeDetector;
use PHPUnit\Framework\TestCase;

final class TransformerWithoutTsTypeDetectorTest extends TestCase
{
    private function findingCount(string $php): int
    {
        return count(new TransformerWithoutTsTypeDetector()->find(Codebase::fromString($php)));
    }

    public function test_flags_a_custom_transformer_without_a_ts_type(): void
    {
        $php = <<<'PHP'
        <?php
        namespace App;
        use Spatie\LaravelData\Data;
        use Spatie\LaravelData\Attributes\WithTransformer;
        final class Money {}
        final class MoneyTransformer {}
        final class PriceData extends Data {
            public function __construct(
                #[WithTransformer(MoneyTransformer::class)]
                public readonly Money $price,
            ) {}
        }
        PHP;

        $this->assertSame(1, $this->findingCount($php));
    }

    public function test_does_not_flag_when_paired_with_ts_type(): void
    {
        $php = <<<'PHP'
        <?php
        namespace App;
        use Spatie\LaravelData\Data;
        use Spatie\LaravelData\Attributes\WithTransformer;
        use Spatie\TypeScriptTransformer\Attributes\TypeScriptType;
        final class Money {}
        final class MoneyTransformer {}
        final class PriceData extends Data {
            public function __construct(
                #[WithTransformer(MoneyTransformer::class), TypeScriptType('string')]
                public readonly Money $price,
            ) {}
        }
        PHP;

        $this->assertSame(0, $this->findingCount($php));
    }

    public function test_does_not_flag_when_paired_with_literal_ts_type(): void
    {
        $php = <<<'PHP'
        <?php
        namespace App;
        use Spatie\LaravelData\Data;
        use Spatie\LaravelData\Attributes\WithTransformer;
        use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
        final class Money {}
        final class MoneyTransformer {}
        final class PriceData extends Data {
            public function __construct(
                #[LiteralTypeScriptType('string'), WithTransformer(MoneyTransformer::class)]
                public readonly Money $price,
            ) {}
        }
        PHP;

        $this->assertSame(0, $this->findingCount($php));
    }

    public function test_does_not_flag_a_known_builtin_transformer(): void
    {
        // The generator already maps DateTimeInterface -> string; no annotation needed.
        $php = <<<'PHP'
        <?php
        namespace App;
        use Spatie\LaravelData\Data;
        use Spatie\LaravelData\Attributes\WithTransformer;
        use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;
        final class ArtistData extends Data {
            public function __construct(
                #[WithTransformer(DateTimeInterfaceTransformer::class)]
                public readonly \Carbon\Carbon $birthDate,
            ) {}
        }
        PHP;

        $this->assertSame(0, $this->findingCount($php));
    }

    public function test_does_not_flag_a_transformer_outside_a_data_class(): void
    {
        $php = <<<'PHP'
        <?php
        namespace App;
        use Spatie\LaravelData\Attributes\WithTransformer;
        final class MoneyTransformer {}
        final class PlainThing {
            public function __construct(
                #[WithTransformer(MoneyTransformer::class)]
                public readonly int $x,
            ) {}
        }
        PHP;

        $this->assertSame(0, $this->findingCount($php));
    }
}
