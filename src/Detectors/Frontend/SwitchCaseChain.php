<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Frontend;

use JesseGall\CodeCommandments\Scribes\Span;
use JesseGall\CodeCommandments\Vue\Directive;
use JesseGall\CodeCommandments\Vue\Element;
use JesseGall\CodeCommandments\Vue\ElementMatch;
use JesseGall\CodeCommandments\Vue\Expr\Expr;
use JesseGall\CodeCommandments\Vue\Expr\Parser;

/**
 * A `v-if` / `v-else-if` [/ `v-else`] chain read off the template as a switch: one
 * subject, equality-tested against a case per branch. Shared by the detector (which
 * just asks "is this a chain?") and the {@see \JesseGall\CodeCommandments\Scribes\Frontend\SwitchCaseScribe}
 * (which needs the subject, the case keys, and each branch element to rewrite).
 *
 * {@see at} returns null unless the head is a `v-if` equality whose every `v-else-if`
 * tests the SAME subject, across at least two cases — so a genuine conditional (mixed
 * subjects, `>`/method guards, a lone `v-if`) is never mistaken for a switch.
 */
final class SwitchCaseChain
{
    private const int CASES = 2;

    /**
     * @param  list<array{key: ?string, element: Element}>  $branches  key null = the `v-else` default
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
        $tail = $this->branches[count($this->branches) - 1]['element'];

        return new Span($this->head->file(), $this->head->sfc->source, $this->head->start, $tail->end);
    }

    public static function at(ElementMatch $head): ?self
    {
        if (! $head->hasAttribute(Directive::If)) {
            return null;
        }

        [$subject, $key] = self::equality($head->attribute(Directive::If));

        if ($subject === null) {
            return null;
        }

        $branches = [['key' => $key, 'element' => $head]];

        foreach ($head->followingElements() as $sibling) {
            if ($sibling->hasAttribute(Directive::ElseIf)) {
                [$next, $caseKey] = self::equality($sibling->attribute(Directive::ElseIf));

                if ($next !== $subject) {
                    return null;
                }

                $branches[] = ['key' => $caseKey, 'element' => $sibling];

                continue;
            }

            if ($sibling->hasAttribute(Directive::Else)) {
                $branches[] = ['key' => null, 'element' => $sibling];
            }

            break;
        }

        $cases = count(array_filter($branches, static fn (array $branch): bool => $branch['key'] !== null));

        return $cases >= self::CASES ? new self($subject, $branches, $head) : null;
    }

    /**
     * Split a `subject === literal` test into [subject, caseKey] by reading the
     * parsed expression: the top node must be an `===`/`==` whose left is a
     * variable / member chain and whose right is a single literal. A compound
     * `a === 'x' || a === 'y'` parses to a top-level `||`, not an equality, so it
     * structurally disqualifies the chain — no pattern matching.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private static function equality(?string $expression): array
    {
        if ($expression === null) {
            return [null, null];
        }

        $node = Parser::parse($expression);

        if (! $node->is(Expr::BINARY) || ! in_array($node->get('op'), ['===', '=='], true)) {
            return [null, null];
        }

        $left = $node->get('left');
        $right = $node->get('right');

        if (! $left instanceof Expr || ! $right instanceof Expr || ! $right->is(Expr::LITERAL)) {
            return [null, null];
        }

        if (! in_array($left->kind, [Expr::IDENTIFIER, Expr::MEMBER, Expr::INDEX], true)) {
            return [null, null];
        }

        return [$left->source(), (string) $right->get('value')];
    }
}
