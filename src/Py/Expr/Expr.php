<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py\Expr;

use JesseGall\CodeCommandments\ExpressionTree;
use JesseGall\CodeCommandments\Positioned;
use JesseGall\CodeCommandments\SyntaxExpression;

/**
 * A node of a parsed Python expression — a kind and the properties that kind carries, shaped like the
 * TypeScript engine's {@see \JesseGall\CodeCommandments\Ts\Expr\Expr} so a tool reads either the same way.
 */
final class Expr implements SyntaxExpression
{
    use ExpressionTree;
    use Positioned;

    /**
     * @param  array<string, mixed>  $props
     */
    public function __construct(
        public readonly ExprKind $kind,
        public readonly array $props = [],
    ) {}

    public function isCall(): bool
    {
        return $this->kind === ExprKind::Call;
    }

    /**
     * What an `==` test is ABOUT — the side that is not the constant, for `x == 'a'` and `'a' == x`
     * alike. Named as the TypeScript engine names it; the unknown expression when this is not a single
     * `==`, or when both sides or neither are constants.
     */
    public function comparisonSubject(): self
    {
        if ($this->kind !== ExprKind::Compare || $this->get('operators') !== ['==']) {
            return new self(ExprKind::Unknown);
        }

        [$left, $right] = $this->get('operands');

        return match (true) {
            ! $left->isConstant() && $right->isConstant() => $left,
            $left->isConstant() && ! $right->isConstant() => $right,
            default => new self(ExprKind::Unknown),
        };
    }

    /**
     * Is this a value that says "nothing" — `None`, `False`, or an empty string, list, tuple, dict or
     * set? Named as the backend names it: what a swallowed failure hands back instead of itself.
     */
    public function isAbsenceValue(): bool
    {
        if ($this->kind === ExprKind::Literal) {
            return $this->get('type')->isAbsence((string) $this->get('value'));
        }

        return $this->kind->isDisplay() && count($this->flatten()) === 1;
    }

    /**
     * The dotted name this expression reads — `Exception`, `errors.Refused` — or empty when it is not a
     * plain name or attribute chain.
     */
    public function dottedName(): string
    {
        if ($this->kind === ExprKind::Name) {
            return (string) $this->get('name');
        }

        $object = $this->kind === ExprKind::Attribute ? $this->get('object')->dottedName() : '';

        return $object === '' ? '' : "{$object}.{$this->get('name')}";
    }

    /**
     * Is this an empty scalar written out — `""`, `0`, `False` — a value that stands in for data rather
     * than being any? An empty collection is not one: "no items" is a real answer.
     */
    public function isEmptyScalar(): bool
    {
        return $this->kind === ExprKind::Literal && $this->get('type')->isEmptyScalar((string) $this->get('value'));
    }

    /**
     * Any literal — an f-string is its own kind, as it computes its fields.
     */
    public function isConstant(): bool
    {
        return $this->kind === ExprKind::Literal;
    }

    /**
     * What this literal holds — null for an expression that is no literal.
     */
    public function literalType(): ?LiteralType
    {
        return $this->kind === ExprKind::Literal ? $this->get('type') : null;
    }
}
