<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py\Expr;

use JesseGall\CodeCommandments\ExpressionTree;
use JesseGall\CodeCommandments\Positioned;
use JesseGall\CodeCommandments\Py\StructuralHash;
use JesseGall\CodeCommandments\SyntaxExpression;
use JesseGall\PhpTypes\Option;

/**
 * A node of a parsed Python expression — a kind and the properties that kind carries, shaped like the
 * TypeScript engine's {@see \JesseGall\CodeCommandments\Ts\Expr\Expr} so a tool reads either the same way.
 */
final class Expr implements SyntaxExpression
{
    use ExpressionTree;
    use Positioned;

    /**
     * The annotations that spell a dict of any kind.
     */
    private const array DICT_TYPES = ['dict', 'Dict', 'typing.Dict', 'Mapping', 'typing.Mapping', 'MutableMapping', 'typing.MutableMapping', 'collections.abc.Mapping'];

    /**
     * @param  array<string, mixed>  $props
     */
    public function __construct(
        public readonly ExprKind $kind,
        public readonly array $props = [],
    ) {}

    public function kindName(): string
    {
        return $this->kind->value;
    }

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
     * Does this annotation spell a dict — `dict`, `dict[str, Any]`, `Mapping[str, object]` and the like?
     */
    public function isDictType(): bool
    {
        $named = $this->kind === ExprKind::Subscript ? $this->get('object') : $this;

        return in_array($named->dottedName(), self::DICT_TYPES, true);
    }

    /**
     * What this reads a string key out of — `row` in `row["sku"]` and `row.get("sku")`. None for any
     * other expression.
     *
     * @return Option<self>
     */
    public function stringKeyBase(): Option
    {
        if ($this->kind === ExprKind::Subscript && $this->get('index')->literalType()?->isText() === true) {
            return Option::some($this->get('object'));
        }

        $callee = $this->isCall() ? $this->get('callee') : null;
        $key = $this->isCall() ? ($this->get('arguments')[0] ?? null) : null;
        $readsKey = $callee?->is(ExprKind::Attribute) === true && $callee->get('name') === 'get' && $key?->literalType()?->isText() === true;

        return $readsKey ? Option::some($callee->get('object')) : Option::none();
    }

    /**
     * Is this $other itself, or the same expression written again?
     */
    public function isSame(self $other): bool
    {
        return $this === $other || StructuralHash::ofExpression($this) === StructuralHash::ofExpression($other);
    }

    /**
     * The key this reads one of $names by — `key` in `raw.get(key)` or `raw[key]` when `raw` is one of
     * them. None for any other expression.
     *
     * @param  list<string>  $names
     * @return Option<self>
     */
    public function keyReadOf(array $names): Option
    {
        if ($this->kind === ExprKind::Subscript && in_array($this->get('object')->dottedName(), $names, true)) {
            return Option::some($this->get('index'));
        }

        $callee = $this->isCall() ? $this->get('callee') : null;
        $reads = $callee?->is(ExprKind::Attribute) === true && $callee->get('name') === 'get' && in_array($callee->get('object')->dottedName(), $names, true);

        return $reads ? Option::fromNullable($this->get('arguments')[0] ?? null) : Option::none();
    }

    /**
     * The value this falls back to when its subject is missing — `d` in `x or d`, `x if x else d` and
     * `x if x is not None else d`. None for any other expression, and for `c and a or b`, which is a
     * conditional written the old way rather than a default.
     *
     * @return Option<self>
     */
    public function fallback(): Option
    {
        if ($this->kind === ExprKind::Binary && $this->get('op') === 'or') {
            $left = $this->get('left');

            return $left->is(ExprKind::Binary) && $left->get('op') === 'and' ? Option::none() : Option::some($this->get('right'));
        }

        if ($this->kind !== ExprKind::Conditional) {
            return Option::none();
        }

        $test = $this->get('test');
        $subject = $this->get('then');
        $isNotNone = $test->is(ExprKind::Compare) && $test->get('operators') === ['is not']
            && $test->get('operands')[0]->isSame($subject) && $test->get('operands')[1]->literalType() === LiteralType::None;

        return $test->isSame($subject) || $isNotNone ? Option::some($this->get('else')) : Option::none();
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
