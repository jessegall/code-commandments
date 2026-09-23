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
     * The annotations that spell a callable.
     */
    private const array CALLABLE_TYPES = ['Callable', 'typing.Callable', 'collections.abc.Callable'];

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

        return $this->isEmptyCollection();
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
     * The value this falls back to when its subject is missing — `d` in `x or d`, `x if x else d`,
     * `x if x is not None else d` and `m.get(k, d)`. None for any other expression, and for `c and a or b`,
     * which is a conditional written the old way rather than a default.
     *
     * @return Option<self>
     */
    public function fallback(): Option
    {
        return $this->defaulted()->map(static fn (array $pair): self => $pair[1]);
    }

    /**
     * What this reads before falling back — `x` in `x or d` and its conditional spellings, `m` in
     * `m.get(k, d)` — the value whose absence the default answers.
     *
     * @return Option<self>
     */
    public function fallbackSubject(): Option
    {
        return $this->defaulted()->map(static fn (array $pair): self => $pair[0]);
    }

    /**
     * Is this a string or a number written out on one line — a value you compare and dispatch on, not a
     * document (a query, a template) that merely happens to be text?
     */
    public function isScalarValue(): bool
    {
        return $this->literalType()?->isScalar() === true && ! str_contains((string) $this->get('value'), "\n");
    }

    /**
     * Is this the blank string written out — `''` or `""`?
     */
    public function isBlankString(): bool
    {
        return $this->literalType() === LiteralType::String && $this->get('value') === '';
    }

    /**
     * Does this ask whether $dotted is blank — `x == ''`, `x != ''`, `not x`?
     */
    public function testsBlanknessOf(string $dotted): bool
    {
        if ($this->kind === ExprKind::Unary) {
            return $this->get('op') === 'not' && $this->get('operand')->dottedName() === $dotted;
        }

        if ($this->kind !== ExprKind::Compare || ! in_array($this->get('operators'), [['=='], ['!=']], true)) {
            return false;
        }

        [$left, $right] = $this->get('operands');

        return ($left->dottedName() === $dotted && $right->isBlankString()) || ($right->dottedName() === $dotted && $left->isBlankString());
    }

    /**
     * Is this one `==` or `!=` between $subject and a value the same as $other, either way round?
     */
    public function equatesWith(self $subject, self $other): bool
    {
        if ($this->kind !== ExprKind::Compare || ! in_array($this->get('operators'), [['=='], ['!=']], true)) {
            return false;
        }

        [$left, $right] = $this->get('operands');

        return ($left === $subject && $right->isSame($other)) || ($right === $subject && $left->isSame($other));
    }

    /**
     * Is this a `*` or `**` spread of a conditional between an empty collection and one written out —
     * `**({'k': v} if v else {})` — an entry built and included only when a condition holds?
     */
    public function isConditionalSpread(): bool
    {
        if ($this->kind !== ExprKind::Starred || ! $this->get('value')->is(ExprKind::Conditional)) {
            return false;
        }

        [$then, $else] = [$this->get('value')->get('then'), $this->get('value')->get('else')];

        return ($then->isEmptyCollection() && $else->kind->isDisplay()) || ($else->isEmptyCollection() && $then->kind->isDisplay());
    }

    /**
     * Does this annotation spell a callable that may be `None` — `Callable[..] | None`, `None | Callable`,
     * `Optional[Callable[..]]`?
     */
    public function isOptionalCallableType(): bool
    {
        if ($this->kind === ExprKind::Subscript && in_array($this->get('object')->dottedName(), ['Optional', 'typing.Optional'], true)) {
            return $this->get('index')->isCallableType();
        }

        if ($this->kind !== ExprKind::Binary || $this->get('op') !== '|') {
            return false;
        }

        [$left, $right] = [$this->get('left'), $this->get('right')];

        return ($left->isCallableType() && $right->literalType() === LiteralType::None) || ($right->isCallableType() && $left->literalType() === LiteralType::None);
    }

    /**
     * Does this annotation spell a callable — `Callable`, `Callable[[int], None]`, spelled through
     * `typing` or `collections.abc` or bare?
     */
    public function isCallableType(): bool
    {
        $named = $this->kind === ExprKind::Subscript ? $this->get('object') : $this;

        return in_array($named->dottedName(), self::CALLABLE_TYPES, true);
    }

    /**
     * Does this ask whether $dotted holds nothing — `len(x) == 0`?
     */
    public function testsEmptinessOf(string $dotted): bool
    {
        if ($this->kind !== ExprKind::Compare || $this->get('operators') !== ['==']) {
            return false;
        }

        [$measured, $zero] = $this->get('operands');
        $arguments = $measured->isCall() ? $measured->get('arguments') : [];

        return $measured->isCall() && $measured->get('callee')->dottedName() === 'len' && count($arguments) === 1
            && $arguments[0]->dottedName() === $dotted && $zero->literalType() === LiteralType::Number && $zero->get('value') === '0';
    }

    /**
     * Does this ask whether $dotted is `None` — `x is None`, `x is not None`?
     */
    public function testsNoneOf(string $dotted): bool
    {
        if ($this->kind !== ExprKind::Compare || ! in_array($this->get('operators'), [['is'], ['is not']], true)) {
            return false;
        }

        [$left, $right] = $this->get('operands');

        return ($left->dottedName() === $dotted && $right->literalType() === LiteralType::None) || ($right->dottedName() === $dotted && $left->literalType() === LiteralType::None);
    }

    /**
     * Is this an `and` or an `or` — an operator that may leave its right side unrun?
     */
    public function isShortCircuit(): bool
    {
        return $this->kind === ExprKind::Binary && in_array($this->get('op'), ['and', 'or'], true);
    }

    /**
     * Is this a conditional expression that holds another in a branch — `a if x else b if y else c`,
     * `(a if y else b) if x else c` — at any depth, a call or a collection in between included?
     */
    public function nestsConditional(): bool
    {
        if ($this->kind !== ExprKind::Conditional) {
            return false;
        }

        $branches = [...$this->get('then')->flatten(), ...$this->get('else')->flatten()];

        return array_any($branches, static fn (self $branch): bool => $branch->is(ExprKind::Conditional));
    }

    /**
     * An empty list, tuple, dict or set written out — "no items", as a value.
     */
    public function isEmptyCollection(): bool
    {
        return $this->kind->isDisplay() && count($this->flatten()) === 1;
    }

    /**
     * The name a chain of reads starts from — `order` in `order.lines[0].sku` and `order.total()` —
     * empty when it starts from anything but a name.
     */
    public function rootName(): string
    {
        return match ($this->kind) {
            ExprKind::Name => (string) $this->get('name'),
            ExprKind::Attribute, ExprKind::Subscript => $this->get('object')->rootName(),
            ExprKind::Call => $this->get('callee')->rootName(),
            default => '',
        };
    }

    /**
     * The first attribute a chain of reads takes off its root name — `self.client` in
     * `self.client.rows().first()` — empty when the chain starts from anything but a name.
     */
    public function reachedThrough(): string
    {
        return match ($this->kind) {
            ExprKind::Attribute => $this->get('object')->is(ExprKind::Name) ? $this->dottedName() : $this->get('object')->reachedThrough(),
            ExprKind::Subscript => $this->get('object')->reachedThrough(),
            ExprKind::Call => $this->get('callee')->reachedThrough(),
            default => '',
        };
    }

    /**
     * The subject and the default of a defaulted read, in that order.
     *
     * @return Option<array{self, self}>
     */
    private function defaulted(): Option
    {
        if ($this->kind === ExprKind::Binary && $this->get('op') === 'or') {
            $left = $this->get('left');

            return $left->is(ExprKind::Binary) && $left->get('op') === 'and' ? Option::none() : Option::some([$left, $this->get('right')]);
        }

        if ($this->isCall()) {
            return $this->keyedDefault();
        }

        if ($this->kind !== ExprKind::Conditional) {
            return Option::none();
        }

        $test = $this->get('test');
        $subject = $this->get('then');
        $isNotNone = $test->is(ExprKind::Compare) && $test->get('operators') === ['is not']
            && $test->get('operands')[0]->isSame($subject) && $test->get('operands')[1]->literalType() === LiteralType::None;

        return $test->isSame($subject) || $isNotNone ? Option::some([$subject, $this->get('else')]) : Option::none();
    }

    /**
     * `m.get(k, d)` read as its mapping and its default — two plain arguments, the second the default.
     *
     * @return Option<array{self, self}>
     */
    private function keyedDefault(): Option
    {
        $callee = $this->get('callee');
        $arguments = $this->get('arguments');
        $plain = array_filter($arguments, static fn (self $argument): bool => ! $argument->is(ExprKind::Keyword) && ! $argument->is(ExprKind::Starred));

        if (! $callee->is(ExprKind::Attribute) || $callee->get('name') !== 'get' || count($arguments) !== 2 || count($plain) !== 2) {
            return Option::none();
        }

        return Option::some([$callee->get('object'), $arguments[1]]);
    }

    /**
     * Any literal — an f-string is its own kind, as it computes its fields.
     */
    public function isConstant(): bool
    {
        return $this->kind === ExprKind::Literal;
    }

    /**
     * @return list<static>
     */
    public function answers(): array
    {
        return [$this];
    }

    /**
     * What this literal holds — null for an expression that is no literal.
     */
    public function literalType(): ?LiteralType
    {
        return $this->kind === ExprKind::Literal ? $this->get('type') : null;
    }
}
