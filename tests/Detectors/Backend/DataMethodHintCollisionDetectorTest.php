<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\DataMethodHintCollisionDetector;
use PHPUnit\Framework\TestCase;

final class DataMethodHintCollisionDetectorTest extends TestCase
{
    public function test_flags_a_method_tag_that_redeclares_a_real_method(): void
    {
        $code = <<<'PHP'
        <?php
        namespace Spatie\LaravelData { class Data {} }
        namespace App {
            use Spatie\LaravelData\Data;
            use App\Models\Credential;

            /**
             * @method static static forCredential(Credential $credential)
             */
            final class CredentialData extends Data
            {
                public static function forCredential(Credential $credential): self
                {
                    return self::from([]);
                }
            }
        }
        PHP;

        $hits = (new DataMethodHintCollisionDetector)->find(Codebase::fromString($code));
        $names = array_map(static fn ($m): string => $m->enclosingClassName() ?? '?', $hits);

        $this->assertSame(['App\\CredentialData'], $names);
    }

    public function test_does_not_flag_a_method_tag_describing_the_magic_from_overload(): void
    {
        // The righteous twin: @method documents `from` (the invisible magic overload),
        // NOT the concrete `fromCredential` factory — so no real method is re-declared.
        $code = <<<'PHP'
        <?php
        namespace Spatie\LaravelData { class Data {} }
        namespace App {
            use Spatie\LaravelData\Data;
            use App\Models\Credential;

            /**
             * @method static static from(Credential $credential)
             */
            final class CredentialData extends Data
            {
                public static function fromCredential(Credential $credential): self
                {
                    return self::from([]);
                }
            }
        }
        PHP;

        $hits = (new DataMethodHintCollisionDetector)->find(Codebase::fromString($code));

        $this->assertSame([], $hits);
    }

    public function test_ignores_method_tags_on_non_data_classes(): void
    {
        $code = <<<'PHP'
        <?php
        namespace App {
            /** @method static static make() */
            class Widget { public static function make(): self { return new self; } }
        }
        PHP;

        $this->assertSame([], (new DataMethodHintCollisionDetector)->find(Codebase::fromString($code)));
    }

    public function test_handles_a_conditional_return_type_method_tag(): void
    {
        // The collect() conditional return type contains parens — the parser must still
        // extract `collect` as the method name and detect the collision.
        $code = <<<'PHP'
        <?php
        namespace Spatie\LaravelData { class Data {} }
        namespace App {
            use Spatie\LaravelData\Data;
            use Illuminate\Support\Collection;

            /**
             * @method static ($items is \Illuminate\Support\Collection ? \Illuminate\Support\Collection<int, static> : array<int, static>) collect(iterable $items)
             */
            final class RowData extends Data
            {
                public static function collect(iterable $items): array { return []; }
            }
        }
        PHP;

        $hits = (new DataMethodHintCollisionDetector)->find(Codebase::fromString($code));
        $names = array_map(static fn ($m): string => $m->enclosingClassName() ?? '?', $hits);

        $this->assertSame(['App\\RowData'], $names);
    }
}
