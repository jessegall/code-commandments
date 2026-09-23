<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py\Node;

use Closure;
use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\Expr\ExprKind;
use JesseGall\CodeCommandments\Py\Expr\LiteralType;

/**
 * A `match subject:` with its cases.
 */
final class MatchStmt extends Node
{
    /**
     * @param  list<MatchCase>  $cases
     */
    public function __construct(
        public readonly Expr $subject,
        public readonly array $cases,
    ) {}

    public function children(): array
    {
        return $this->cases;
    }

    public function expressions(): array
    {
        return [$this->subject];
    }

    public function isBranchingConstruct(): bool
    {
        return true;
    }

    /**
     * The literal every `case` tests for, as comparable keys — empty unless every case other than the
     * `_` wildcard is literals alone.
     *
     * @return list<string>
     */
    public function literalCaseKeys(): array
    {
        return $this->caseKeys(static fn (Expr $pattern): bool => $pattern->literalType() !== null);
    }

    /**
     * The strings every `case` tests for, as comparable keys — empty unless every case other than the
     * `_` wildcard is strings alone.
     *
     * @return list<string>
     */
    public function textCaseKeys(): array
    {
        return $this->caseKeys(static fn (Expr $pattern): bool => $pattern->literalType() === LiteralType::String);
    }

    /**
     * The keys of the case alternatives $admits, other than `_` — empty when any alternative is not.
     *
     * @param  Closure(Expr): bool  $admits
     * @return list<string>
     */
    private function caseKeys(Closure $admits): array
    {
        $alternatives = array_merge([], ...array_map(static fn (MatchCase $case): array => $case->pattern->alternatives(), $this->cases));
        $tested = array_values(array_filter($alternatives, static fn (Expr $pattern): bool => $pattern->dottedName() !== '_'));

        return array_all($tested, $admits) ? array_map(static fn (Expr $pattern): string => $pattern->literalKey(), $tested) : [];
    }

    /**
     * The classes whose members the cases other than `_` test for — `Status` for `case Status.PAID:` —
     * empty unless every such alternative is a `Class.MEMBER`.
     *
     * @return list<string>
     */
    public function memberCaseClasses(): array
    {
        $handled = array_filter($this->cases, static fn (MatchCase $case): bool => ! $case->isWildcard());
        $alternatives = array_merge([], ...array_map(static fn (MatchCase $case): array => $case->pattern->alternatives(), array_values($handled)));
        $members = array_filter($alternatives, static fn (Expr $pattern): bool => $pattern->is(ExprKind::Attribute) && $pattern->get('object')->is(ExprKind::Name));

        return $alternatives !== [] && count($members) === count($alternatives)
            ? array_values(array_unique(array_map(static fn (Expr $member): string => $member->get('object')->dottedName(), $members)))
            : [];
    }

    /**
     * Does the `_` arm answer with nothing while every handled arm answers with something?
     */
    public function onlyTheWildcardAnswersAbsence(): bool
    {
        $wildcard = array_filter($this->cases, static fn (MatchCase $case): bool => $case->isWildcard());
        $handled = array_filter($this->cases, static fn (MatchCase $case): bool => ! $case->isWildcard());

        return array_any($wildcard, static fn (MatchCase $case): bool => $case->isAnswerAbsent())
            && ! array_any($handled, static fn (MatchCase $case): bool => $case->isAnswerAbsent());
    }
}
