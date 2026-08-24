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

        // The arrow-fn tuple is reported against the METHOD that declares it: a closure has no name of
        // its own, and the work it does is the declaring method's.
        $this->assertSame(['S::deferred', 'S::split'], $scopes);
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
            // three lists of the same type concatenated — no position means anything
            public function interactions(array $performed, array $asked, array $puts): array {
                return [...$performed, ...$asked, ...$puts];
            }
            // a spread among plain items is still not a tuple — the length is unknown
            public function withHead(array $rest, int $a, int $b): array {
                return [$a, $b, ...$rest];
            }
        }
        PHP;

        $this->assertSame([], (new PositionalTupleReturnDetector)->find(Codebase::fromString($code)));
    }

    public function test_a_declared_sequence_return_is_not_a_tuple(): void
    {
        // #455/#461/#476: a RUN of steps, spliced into a sequence by the caller and never
        // destructured. `list<T>`/`array<int, T>`/`T[]` each say "N of one kind, order is the
        // meaning" — the opposite of a tuple, and statically checked. Take the author at their word.
        $code = <<<'PHP'
        <?php
        class S {
            /** @return list<Command|Instruction> */
            public function beginPull(Client $client): array {
                return [
                    $client->mount($this->plane),
                    $this->startPulling($client),
                    $client->listen($this->plane),
                    $this->requestInvitation($client),
                ];
            }
            /** @return array<int, Step> */
            public function keyed(Client $client): array {
                return [$client->a(), $this->b(), $client->c()];
            }
            /** @return Step[] */
            public function bracketed(Client $client): array {
                return [$client->a(), $this->b(), $client->c()];
            }
        }
        PHP;

        $this->assertSame([], (new PositionalTupleReturnDetector)->find(Codebase::fromString($code)));
    }

    public function test_a_numeric_shape_annotation_is_still_a_tuple(): void
    {
        // The guard is about a SEQUENCE, not about carrying any annotation: `array{0: …, 1: …}`
        // documents the very positions the sin is about, so it must not buy an exemption.
        $code = <<<'PHP'
        <?php
        class S {
            /** @return array{0: list<string>, 1: list<string>, 2: int} */
            public function partition(Rows $rows): array {
                return [$rows->valid(), $this->invalid($rows), $rows->count()];
            }
        }
        PHP;

        $this->assertCount(1, (new PositionalTupleReturnDetector)->find(Codebase::fromString($code)));
    }
}
