<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\DerivedArgumentDetector;
use PHPUnit\Framework\TestCase;

/**
 * Requested in #484. The detector is {@see \JesseGall\CodeCommandments\Unpublished} while it calibrates,
 * so it is instantiated directly rather than through the catalog.
 */
final class DerivedArgumentDetectorTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function locations(string $code): array
    {
        return array_map(
            static fn ($m): string => $m->location(),
            (new DerivedArgumentDetector)->find(Codebase::fromString($code)),
        );
    }

    public function test_flags_a_call_handed_the_subject_and_a_projection_of_it(): void
    {
        $code = <<<'PHP'
        <?php
        final class ImportRequest {
            public function getShopChannelId(): string { return 'c'; }
        }
        final class Importer {
            public function run(ImportRequest $request): void {
                $this->persist($request, $request->getShopChannelId());
            }
            private function persist(ImportRequest $request, string $channelId): void {}
        }
        PHP;

        $this->assertCount(1, $this->locations($code));
    }

    public function test_flags_two_different_projections_of_one_subject(): void
    {
        // No subject passed whole, but the callee is handed the same value flattened two ways — it could
        // have taken `$result` and read both off it.
        $code = <<<'PHP'
        <?php
        final class ProcessResult {
            public function output(): string { return ''; }
            public function exitCode(): int { return 0; }
        }
        final class Agent {
            public function reportsLoggedIn(string $output, int $code): bool { return $code === 0; }
        }
        final class SignIn {
            public function check(Agent $agent, ProcessResult $result): bool {
                return $agent->reportsLoggedIn($result->output(), $result->exitCode());
            }
        }
        PHP;

        $this->assertCount(1, $this->locations($code));
    }

    public function test_flags_a_constructor_the_same_way(): void
    {
        $code = <<<'PHP'
        <?php
        final class Boundary {
            public function props(): array { return []; }
        }
        final class Extraction {
            public function __construct(Boundary $boundary, array $props) {}
        }
        final class Scribe {
            public function build(Boundary $boundary): Extraction {
                return new Extraction($boundary, $boundary->props());
            }
        }
        PHP;

        $this->assertCount(1, $this->locations($code));
    }

    public function test_does_not_flag_a_subject_reached_only_once(): void
    {
        // A scalar utility handed one projection proves nothing — `isThirdPerson(string $name)` has no
        // business learning what a node is, and every codebase is full of this.
        $code = <<<'PHP'
        <?php
        final class Method {
            public function name(): string { return 'x'; }
        }
        final class Mood {
            public static function isThirdPerson(string $name): bool { return true; }
        }
        final class Checker {
            public function check(Method $method): bool {
                return Mood::isThirdPerson($method->name());
            }
        }
        PHP;

        $this->assertSame([], $this->locations($code));
    }

    public function test_does_not_flag_a_parameter_that_already_takes_the_subject(): void
    {
        // Both positions want the object, so nothing was flattened.
        $code = <<<'PHP'
        <?php
        final class Turn { public function parent(): Turn { return $this; } }
        final class Sink {
            public function accept(Turn $a, Turn $b): void {}
        }
        final class Caller {
            public function run(Sink $sink, Turn $turn): void {
                $sink->accept($turn, $turn->parent());
            }
        }
        PHP;

        $this->assertSame([], $this->locations($code));
    }

    public function test_does_not_flag_the_callers_own_state_reached_twice(): void
    {
        // `$this` is not a subject any callee could be handed.
        $code = <<<'PHP'
        <?php
        final class Writer {
            public function write(string $a, string $b): void {}
        }
        final class Report {
            private string $title = 't';
            public function head(): string { return 'h'; }
            public function body(): string { return 'b'; }
            public function emit(Writer $writer): void {
                $writer->write($this->head(), $this->body());
            }
        }
        PHP;

        $this->assertSame([], $this->locations($code));
    }

    public function test_does_not_flag_two_projections_of_different_subjects(): void
    {
        $code = <<<'PHP'
        <?php
        final class Left { public function name(): string { return 'l'; } }
        final class Right { public function name(): string { return 'r'; } }
        final class Pair {
            public function join(string $a, string $b): string { return $a . $b; }
        }
        final class Caller {
            public function run(Pair $pair, Left $left, Right $right): string {
                return $pair->join($left->name(), $right->name());
            }
        }
        PHP;

        $this->assertSame([], $this->locations($code));
    }
}
