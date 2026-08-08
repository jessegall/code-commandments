<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Ts;

use JesseGall\CodeCommandments\Vue\Codebase;
use JesseGall\CodeCommandments\Ts\ExprMatch;
use JesseGall\CodeCommandments\Ts\NodeMatch;
use PHPUnit\Framework\TestCase;

/**
 * The query surface over plain TypeScript — a selector opens a query, `where`/`reject` narrow it, a
 * terminal returns matches that know their `file:line`, exactly as the template and backend engines
 * do. Every rule here is one a detector could not be written for before (#442, #444, #452, #454).
 */
final class QuerySurfaceTest extends TestCase
{
    private const string MODULE = <<<'TS'
        export class Protocol {
            private readonly queue: Instruction[] = [];

            send(payload: string | null): void {
                if (payload === null) {
                    throw new ProtocolError('empty payload');
                }

                const target = document.querySelector('[data-tag=surface]');

                this.transmit(target ?? document.body, payload);
            }
        }

        export function debounce(ms?: number): number {
            return ms ?? 250;
        }
        TS;

    private function codebase(): Codebase
    {
        return Codebase::fromTypeScript(self::MODULE, 'client/protocol.ts');
    }

    public function test_declarations_are_reachable_with_their_file_and_line(): void
    {
        $classes = $this->codebase()->whereClass()->get();
        $functions = $this->codebase()->whereFunction()->get();

        $this->assertSame(['client/protocol.ts:1'], array_map(static fn (NodeMatch $m): string => $m->location(), $classes));
        $this->assertSame('Protocol', $classes[0]->name());

        // A `function` declaration and a class method are both functions — a naming rule asks one question.
        $this->assertSame(['send', 'debounce'], array_map(static fn (NodeMatch $m): string => $m->name(), $functions));
    }

    public function test_parameters_and_fields_are_reachable(): void
    {
        $this->assertSame(['payload', 'ms'], array_map(
            static fn (NodeMatch $m): string => $m->name(),
            $this->codebase()->whereParameter()->get(),
        ));

        $this->assertSame(['queue'], array_map(
            static fn (NodeMatch $m): string => $m->name(),
            $this->codebase()->whereField()->get(),
        ));
    }

    public function test_statements_inside_a_method_body_are_reachable(): void
    {
        $this->assertSame(['client/protocol.ts:5'], array_map(
            static fn (NodeMatch $m): string => $m->location(),
            $this->codebase()->whereIf()->get(),
        ));
    }

    /**
     * The absence rule's opening move, on the frontend — and it reaches an expression nested inside a
     * call argument, not just a statement's own.
     */
    public function test_coalesce_defaults_are_reachable_with_their_own_position(): void
    {
        $this->assertSame([
            'client/protocol.ts:11 → target ?? document.body',
            'client/protocol.ts:16 → ms ?? 250',
        ], array_map(
            static fn (ExprMatch $m): string => $m->location() . ' → ' . $m->scope(),
            $this->codebase()->whereCoalesce()->get(),
        ));
    }

    /**
     * A call in a `const` INITIALIZER is a call like any other — the declaration holds expressions
     * too, so a rule is not blind to most of what a module does.
     */
    public function test_a_call_in_a_declaration_initializer_is_reachable(): void
    {
        $this->assertSame(["document.querySelector('[data-tag=surface]')"], array_map(
            static fn (ExprMatch $m): string => $m->scope(),
            $this->codebase()->whereCall()->callingMethod('querySelector')->get(),
        ));
    }

    /**
     * The rule #442 wanted and could not write: a module that finds its target with a meaning-selector
     * it invented rather than a name the server sent.
     */
    public function test_a_real_rule_composes_the_way_a_backend_one_does(): void
    {
        $hits = $this->codebase()
            ->whereCall()
            ->callingMethod('querySelector')
            ->where(static fn (ExprMatch $match): bool => $match->expr->argument(0)?->is(\JesseGall\CodeCommandments\Ts\Expr\ExprKind::Literal) === true)
            ->get();

        $this->assertSame(['client/protocol.ts:9'], array_map(static fn (ExprMatch $m): string => $m->location(), $hits));
    }

    /**
     * The offset bug calibration found: a body parser was handed its body's start RELATIVE to its
     * own parent, so a function inside a function lost the outer base and every node under it
     * reported a position drifting further the deeper it sat.
     */
    public function test_a_nested_functions_expressions_report_their_real_position(): void
    {
        $module = <<<'TS'
            export function outer() {
                function inner(value: unknown) {
                    if (value === null) {
                        return 0;
                    }
                }
            }
            TS;

        $hits = Codebase::fromTypeScript($module, 'nested.ts')
            ->whereExpression(static fn ($expr): bool => $expr->isNullComparison())
            ->get();

        $this->assertSame(['nested.ts:3'], array_map(static fn (ExprMatch $m): string => $m->location(), $hits));
        $this->assertSame('value === null', $hits[0]->scope());
    }

    public function test_a_codebase_with_no_vue_at_all_still_has_modules(): void
    {
        $codebase = $this->codebase();

        $this->assertSame([], $codebase->components());
        $this->assertCount(1, $codebase->modules());
    }
}
