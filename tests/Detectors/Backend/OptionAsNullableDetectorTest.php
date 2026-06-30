<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\OptionAsNullableDetector;
use PHPUnit\Framework\TestCase;

final class OptionAsNullableDetectorTest extends TestCase
{
    public function test_flags_option_worn_as_a_nullable(): void
    {
        $code = <<<'PHP'
        <?php
        class Repo {
            public function find(int $id): ?Option { return Option::none(); }
            public function total(int $id): Option { return Option::none(); }
            public function name(int $id): string {
                return $this->find($id)->unwrapOr(null) ?? 'x';
            }
            public function honest(int $id): string {
                return $this->find($id)->unwrapOr('default');
            }
        }
        PHP;

        $hits = (new OptionAsNullableDetector)->find(Codebase::fromString($code));
        $scopes = array_map(static fn ($m): string => $m->scope(), $hits);
        sort($scopes);

        // ?Option return (find) + unwrapOr(null) (name) — not the total() Option,
        // not the unwrapOr('default').
        $this->assertSame(['Repo::find', 'Repo::name'], $scopes);
    }

    public function test_does_not_flag_unwrap_or_null_passed_as_an_argument(): void
    {
        // Adapting an Option to a nullable-sink parameter at the boundary — no ?Option
        // type is exposed; only return/assignment collapses are the costume.
        $code = <<<'PHP'
        <?php
        class Socket {
            public static function make(?array $options): self { return new self; }
        }
        class Expander {
            public function expand(Option $enumValues): Socket {
                return Socket::make(options: $enumValues->unwrapOr(null));
            }
            public function stored(Option $o): ?Option {
                // …but storing/returning the collapse IS still a sin (assignment position).
                $x = $o->unwrapOr(null);
                return $x;
            }
        }
        PHP;

        $hits = (new OptionAsNullableDetector)->find(Codebase::fromString($code));
        $scopes = array_map(static fn ($m): string => $m->scope(), $hits);
        sort($scopes);

        // expand()'s arg-position unwrapOr(null) is exempt; stored() keeps two: the
        // ?Option return type and the assignment-position unwrapOr(null).
        $this->assertSame(['Expander::stored', 'Expander::stored'], $scopes);
    }
}
