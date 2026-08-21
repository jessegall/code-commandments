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

    public function test_flags_a_subject_reassembled_from_three_projections(): void
    {
        // No subject passed whole, but the argument list rebuilds `$result` piece by piece.
        $code = <<<'PHP'
        <?php
        final class ProcessResult {
            public function output(): string { return ''; }
            public function errorOutput(): string { return ''; }
            public function failed(): bool { return false; }
        }
        final class AgentTurn {
            public function __construct(string $out, bool $failed, string $err) {}
        }
        final class Runtime {
            public function turn(ProcessResult $result): AgentTurn {
                return new AgentTurn($result->output(), $result->failed(), $result->errorOutput());
            }
        }
        PHP;

        $this->assertCount(1, $this->locations($code));
    }

    public function test_does_not_flag_two_projections_filling_a_generic_primitive(): void
    {
        // #489: `Header::of(title, subtitle, icon)` is a presentation primitive. Two reads off one
        // subject is it filling slots, not reassembling the subject — and a shared component taught to
        // take `ActionManifest` would import a domain it exists to know nothing about.
        $code = <<<'PHP'
        <?php
        final class ActionManifest {
            public function __construct(public string $label, public string $description) {}
        }
        final class Header {
            public static function of(string $title, string $subtitle, string $icon): self { return new self(); }
        }
        final class ActionDetail {
            public function __construct(public ActionManifest $action) {}
            public function header(): Header {
                return Header::of($this->action->label, $this->action->description, 'zap');
            }
        }
        PHP;

        $this->assertSame([], $this->locations($code));
    }

    public function test_does_not_flag_a_projection_consumed_by_a_different_callee(): void
    {
        // #488: the second reach sits INSIDE a list, computed for whatever consumes that list. `Listen`
        // is handed the array, not the subject a second time, and could not derive the command anyway.
        $code = <<<'PHP'
        <?php
        final class Rendered { public string $id = 'n'; }
        final class ScrollToOffset {
            public static function start(string $node): self { return new self(); }
        }
        final class Listen {
            public function __construct(string $node, string $event, array $run) {}
        }
        final class BindScrollBack {
            public function bind(Rendered $rendered): Listen {
                return new Listen($rendered->id, 'pointerleave', [ScrollToOffset::start($rendered->id)]);
            }
        }
        PHP;

        $this->assertSame([], $this->locations($code));
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

    public function test_does_not_ask_for_a_dependency_that_would_close_a_cycle(): void
    {
        // #514: a persistence row mapped into a pure domain value. The row's namespace already
        // references the domain, so the value type cannot learn the row — the fix this rule would
        // ask for is the cycle NamespaceCycleDetector forbids, and the mapper is the one place
        // allowed to see both.
        $code = <<<'PHP'
        <?php
        namespace App\Models {
            use App\Rewrite\RewriteRule;

            class RuleRow {
                public string $id = '';
                public string $find = '';
                public string $replace = '';

                public function compiled(): RewriteRule { return RewriteRule::manual('', '', ''); }
            }
        }

        namespace App\Rewrite {
            use App\Models\RuleRow;

            final class RewriteRule {
                public static function manual(string $id, string $find, string $replace): self { return new self; }
            }

            final class RuleCompiler {
                public function ruleFor(RuleRow $row): RewriteRule {
                    return RewriteRule::manual($row->id, $row->find, $row->replace);
                }
            }
        }
        PHP;

        $this->assertSame([], $this->locations($code));
    }

    public function test_still_asks_when_the_dependency_would_point_one_way(): void
    {
        // The same mapping where nothing points back: `App\Rewrite` may name `App\Records` freely,
        // so the value type CAN take the row and the rule says so.
        $code = <<<'PHP'
        <?php
        namespace App\Records {
            class RuleRow {
                public string $id = '';
                public string $find = '';
                public string $replace = '';
            }
        }

        namespace App\Rewrite {
            use App\Records\RuleRow;

            final class RewriteRule {
                public static function manual(string $id, string $find, string $replace): self { return new self; }
            }

            final class RuleCompiler {
                public function ruleFor(RuleRow $row): RewriteRule {
                    return RewriteRule::manual($row->id, $row->find, $row->replace);
                }
            }
        }
        PHP;

        $this->assertCount(1, $this->locations($code));
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
