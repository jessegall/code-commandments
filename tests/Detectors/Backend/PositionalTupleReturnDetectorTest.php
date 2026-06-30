<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\PositionalTupleReturnDetector;
use PHPUnit\Framework\TestCase;

final class PositionalTupleReturnDetectorTest extends TestCase
{
    public function test_flags_a_positional_tuple_returned_from_a_method_or_closure(): void
    {
        $code = <<<'PHP'
        <?php
        class S {
            public function split(array $rows): array {
                $valid = [];
                $invalid = [];
                $errors = [];
                return [$valid, $invalid, $errors];
            }
            public function deferred(string $id): callable {
                return fn (string $name, int $count): array => [$id, $name, $count];
            }
        }
        PHP;

        $hits = (new PositionalTupleReturnDetector)->find(Codebase::fromString($code));
        $scopes = array_map(static fn ($m): string => $m->scope(), $hits);
        sort($scopes);

        // The arrow-fn tuple resolves to its enclosing class scope (anonymous fn).
        $this->assertSame(['S', 'S::split'], $scopes);
    }

    public function test_leaves_projections_lists_pairs_and_records_alone(): void
    {
        $code = <<<'PHP'
        <?php
        class S {
            // single-source projection (a row), not a tuple
            public function row(Order $o): array {
                return [$o->id, $o->status, $o->total];
            }
            // a list of literals — a collection
            public function headers(): array {
                return ['Id', 'Status', 'Total'];
            }
            // a two-element pair — below the tuple floor
            public function pair(int $a, int $b): array {
                return [$a, $b];
            }
            // a string-keyed record — ArrayReturnBag's job, not this one
            public function record(int $a, int $b, int $c): array {
                return ['a' => $a, 'b' => $b, 'c' => $c];
            }
        }
        PHP;

        $this->assertSame([], (new PositionalTupleReturnDetector)->find(Codebase::fromString($code)));
    }
}
