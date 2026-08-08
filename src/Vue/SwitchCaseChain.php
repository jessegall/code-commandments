<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

use JesseGall\CodeCommandments\Span;
use JesseGall\CodeCommandments\Ts\Expr\Expr;
use JesseGall\CodeCommandments\Ts\Expr\ExprKind;
use JesseGall\CodeCommandments\Ts\Expr\Parser;
use JesseGall\PhpTypes\Option;

/**
 * Reads a `v-if`/`v-else-if` chain as a switch: one subject, equality-tested per branch.
 * {@see at} returns null unless every `v-else-if` tests the SAME subject across ≥2 cases.
 * Shared by the detector and {@see SwitchCaseScribe}.
 */
final class SwitchCaseChain
{
    private const int CASES = 2;

    /**
     * @param  list<SwitchCase>  $branches
     */
    private function __construct(
        public readonly string $subject,
        public readonly array $branches,
        private readonly ElementMatch $head,
    ) {}

    /**
     * Where the whole chain sits — its head's file/source, spanning the first branch's
     * `<` to past the last — so a scribe replaces it through the same {@see Span} seam
     * any other finding uses.
     */
    public function span(): Span
    {
        $tail = $this->branches[count($this->branches) - 1]->element;

        return new Span($this->head->file(), $this->head->sfc->source, $this->head->start, $tail->end);
    }

    public static function at(ElementMatch $head): ?self
    {
        if (! $head->hasAttribute(Directive::If)) {
            return null;
        }

        $test = self::equality($head->attribute(Directive::If));

        return $test->isNone() ? null : self::from($head, $test->unwrap());
    }

    /**
     * The chain $head opens, given the test it makes — every following `v-else-if` on the SAME
     * subject, then an optional `v-else`. Null when a sibling tests something else (that is two
     * conditionals, not one switch) or when too few cases remain to be worth a `<SwitchCase>`.
     */
    private static function from(ElementMatch $head, EqualityTest $test): ?self
    {
        $branches = [SwitchCase::matching($test->key, $head)];

        foreach ($head->followingElements() as $sibling) {
            if (! $sibling->hasAttribute(Directive::ElseIf)) {
                if ($sibling->hasAttribute(Directive::Else)) {
                    $branches[] = SwitchCase::fallback($sibling);
                }

                break;
            }

            $next = self::equality($sibling->attribute(Directive::ElseIf))
                ->filter(static fn (EqualityTest $each): bool => $each->sharesSubjectWith($test));

            if ($next->isNone()) {
                return null;
            }

            $branches[] = SwitchCase::matching($next->unwrap()->key, $sibling);
        }

        $cases = count(array_filter($branches, static fn (SwitchCase $branch): bool => ! $branch->isFallback()));

        return $cases >= self::CASES ? new self($test->subject, $branches, $head) : null;
    }

    /**
     * Read a `subject === literal` test off the parsed expression: the top node must be an
     * `===`/`==` whose left is a variable / member chain and whose right is a single literal. A
     * compound `a === 'x' || a === 'y'` parses to a top-level `||`, not an equality, so it
     * structurally disqualifies the chain — no pattern matching.
     *
     * @param  Option<string>  $expression
     * @return Option<EqualityTest>
     */
    private static function equality(Option $expression): Option
    {
        if ($expression->isNone()) {
            return Option::none();
        }

        $node = Parser::parse($expression->unwrap());

        if (! $node->is(ExprKind::Binary) || ! in_array($node->get('op'), ['===', '=='], true)) {
            return Option::none();
        }

        $left = $node->get('left');
        $right = $node->get('right');

        if (! $left instanceof Expr || ! $right instanceof Expr || ! $right->is(ExprKind::Literal)) {
            return Option::none();
        }

        if (! in_array($left->kind, [ExprKind::Identifier, ExprKind::Member, ExprKind::Index], true)) {
            return Option::none();
        }

        return Option::some(new EqualityTest($left->source(), (string) $right->get('value')));
    }
}
