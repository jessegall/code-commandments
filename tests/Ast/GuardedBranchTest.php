<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Ast;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use PHPUnit\Framework\TestCase;

final class GuardedBranchTest extends TestCase
{
    public function test_the_tested_expression_is_not_guarded_but_the_branch_it_guards_is(): void
    {
        $guarded = $this->guardedness(<<<'PHP'
        <?php
        class S {
            public function m(): void {
                if (\decides()) { \runsWhenTrue(); } else { \runsWhenFalse(); }
            }
        }
        PHP);

        // `decides()` runs on every call — it IS the question, not something the answer gates.
        $this->assertFalse($guarded['decides']);
        $this->assertTrue($guarded['runsWhenTrue']);
        $this->assertTrue($guarded['runsWhenFalse']);
    }

    public function test_a_short_circuit_and_a_ternary_guard_only_their_right_hand_side(): void
    {
        $guarded = $this->guardedness(<<<'PHP'
        <?php
        class S {
            public function m(): void {
                $a = \leftOfCoalesce() ?? \rightOfCoalesce();
                $b = \leftOfAnd() && \rightOfAnd();
                $c = \ternaryTest() ? \ternaryThen() : \ternaryElse();
            }
        }
        PHP);

        $this->assertFalse($guarded['leftOfCoalesce']);
        $this->assertTrue($guarded['rightOfCoalesce']);
        $this->assertFalse($guarded['leftOfAnd']);
        $this->assertTrue($guarded['rightOfAnd']);
        $this->assertFalse($guarded['ternaryTest']);
        $this->assertTrue($guarded['ternaryThen']);
        $this->assertTrue($guarded['ternaryElse']);
    }

    public function test_a_match_arm_is_guarded_and_the_subject_is_not(): void
    {
        $guarded = $this->guardedness(<<<'PHP'
        <?php
        class S {
            public function m(): string {
                return match (\subject()) { 1 => \armBody(), default => '' };
            }
        }
        PHP);

        $this->assertFalse($guarded['subject']);
        $this->assertTrue($guarded['armBody']);
    }

    public function test_a_closure_declared_inside_a_condition_does_not_make_its_body_guarded(): void
    {
        $guarded = $this->guardedness(<<<'PHP'
        <?php
        class S {
            public function m(): void {
                if (\decides()) {
                    $run = function (): void { \insideClosure(); };
                }
            }
        }
        PHP);

        // The closure's declaration is conditional; what its body does when CALLED is not.
        $this->assertFalse($guarded['insideClosure']);
    }

    public function test_a_call_in_a_plain_body_is_never_guarded(): void
    {
        $guarded = $this->guardedness(<<<'PHP'
        <?php
        class S {
            public function m(): void { \alwaysRuns(); }
        }
        PHP);

        $this->assertFalse($guarded['alwaysRuns']);
    }

    /**
     * @return array<string, bool>  called function name => is it inside a guarded branch
     */
    private function guardedness(string $code): array
    {
        $guarded = [];

        foreach (Codebase::fromString($code)->whereFunction()->get() as $call) {
            $guarded[(string) $call->callName()] = $call->isWithinCondition();
        }

        return $guarded;
    }
}
