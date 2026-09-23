<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py\Expr;

use Closure;
use JesseGall\CodeCommandments\ExpressionTree;
use JesseGall\CodeCommandments\Positioned;
use JesseGall\CodeCommandments\Py\Enums;
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
     * The type this annotation names — `Query` from `Query` and from the forward reference `"Query"`,
     * `typing.Self` — or empty when it names no single type.
     */
    public function spelledType(): string
    {
        return $this->literalType() === LiteralType::String ? (string) $this->get('value') : $this->dottedName();
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
     * Is this a mapping lookup that names its own default — `m.get(k, d)` — the value an absent optional
     * key has, stated where it is read, rather than a `None` papered over after the fact?
     */
    public function isKeyedDefault(): bool
    {
        return $this->isCall() && $this->keyedDefault()->isSome();
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
     * This literal as a comparable key — its type and its value — or empty when it is not a literal.
     */
    public function literalKey(): string
    {
        $type = $this->literalType();

        return $type === null ? '' : "{$type->value}:{$this->get('value')}";
    }

    /**
     * The literals an `in` or `not in` test checks against — `('paid', 'late')` in
     * `x in ('paid', 'late')` — as literal keys: empty unless the right side is a tuple, list or set of
     * two or more strings and nothing else. Numbers are left out: a handful of small integers is a
     * count or an index as often as it is an enum's values, and coincides with one by chance.
     *
     * @return list<string>
     */
    public function membershipLiteralKeys(): array
    {
        if ($this->kind !== ExprKind::Compare || ! in_array($this->get('operators'), [['in'], ['not in']], true)) {
            return [];
        }

        $set = $this->get('operands')[1];
        $elements = $set->kind->isDisplay() && $set->kind !== ExprKind::Dict ? $set->subExpressions() : [];
        $strings = array_all($elements, static fn (self $element): bool => $element->literalType() === LiteralType::String);

        return count($elements) >= 2 && $strings ? array_map(static fn (self $element): string => $element->literalKey(), $elements) : [];
    }

    /**
     * The value this argument hands over — a keyword's value, or the argument itself.
     */
    public function argumentValue(): self
    {
        return $this->kind === ExprKind::Keyword ? $this->get('value') : $this;
    }

    /**
     * Is this a plain read of one of the object's own attributes — `self.number` — a field carried across
     * as it is, rather than a value computed or handed in?
     */
    public function isOwnAttributeRead(): bool
    {
        return $this->kind === ExprKind::Attribute && $this->get('object')->dottedName() === 'self';
    }

    /**
     * Is this `object.__setattr__(self, 'x', …)` for one of $fields — a write past `frozen`?
     *
     * @param  list<string>  $fields
     */
    public function setsOwnAttribute(array $fields): bool
    {
        $arguments = $this->isCall() && $this->get('callee')->dottedName() === 'object.__setattr__' ? $this->get('arguments') : [];

        return count($arguments) === 3 && $arguments[0]->dottedName() === 'self' && in_array($arguments[1]->get('value'), $fields, true);
    }

    /**
     * Does this call build an object of $class — `Order(…)`, `type(self)(…)`, `self.__class__(…)`?
     */
    public function constructs(string $class): bool
    {
        if (! $this->isCall()) {
            return false;
        }

        $callee = $this->get('callee');
        $ofSelf = $callee->isCall() && $callee->get('callee')->dottedName() === 'type';

        return $ofSelf || in_array($callee->dottedName(), [$class, 'self.__class__'], true);
    }

    /**
     * How many string-literal keys this dict display names — none for anything but a dict.
     */
    public function stringKeyCount(): int
    {
        $keys = $this->kind === ExprKind::Dict ? array_filter($this->get('keys')) : [];

        return count(array_filter($keys, static fn (self $key): bool => $key->literalType() === LiteralType::String));
    }

    /**
     * Does this dict display spread another mapping in — `{**base, …}` — so its keys are not all its own?
     */
    public function spreadsAnother(): bool
    {
        return $this->kind === ExprKind::Dict && in_array(null, $this->get('keys'), true);
    }

    /**
     * Does a value of this dict display hold a collection of its own — a payload rather than a record?
     */
    public function hasNestedCollectionValue(): bool
    {
        return $this->kind === ExprKind::Dict && array_any($this->get('values'), static fn (self $value): bool => $value->kind->isDisplay());
    }

    /**
     * Is this dict display a JSON schema — naming a `type` beside `properties` or `items`?
     */
    public function isJsonSchema(): bool
    {
        $keys = $this->kind === ExprKind::Dict ? array_map(static fn (?self $key): string => (string) $key?->get('value'), $this->get('keys')) : [];

        return in_array('type', $keys, true) && (in_array('properties', $keys, true) || in_array('items', $keys, true));
    }

    /**
     * Is every value of this dict display a member of one class — `Colour.GREEN`, `Colour.RED` — a
     * table of interchangeable values keyed by data, with no fields to name?
     */
    public function isMemberTable(): bool
    {
        if ($this->kind !== ExprKind::Dict) {
            return false;
        }

        $values = $this->get('values');
        $members = array_all($values, static fn (self $value): bool => $value->is(ExprKind::Attribute) && $value->get('object')->is(ExprKind::Name));

        return $members && count(array_unique(array_map(static fn (self $member): string => $member->get('object')->dottedName(), $values))) === 1;
    }

    /**
     * The one name every value of this dict display reads its data off — `order` in
     * `{'id': order.id, 'total': round(order.total, 2)}`, the functions it calls aside — empty when they
     * read different names, or none.
     */
    public function projectedName(): string
    {
        $values = $this->kind === ExprKind::Dict ? $this->get('values') : [];
        $names = array_unique(array_merge([], ...array_map(static fn (self $value): array => $value->dataNames(), $values)));

        return count($names) === 1 ? (string) reset($names) : '';
    }

    /**
     * The names this expression reads data from — every name in it but the ones it calls and the ones a
     * comprehension in it binds for itself.
     *
     * @return list<string>
     */
    public function dataNames(): array
    {
        if ($this->kind === ExprKind::Name) {
            return [(string) $this->get('name')];
        }

        $parts = $this->isCall() && $this->get('callee')->is(ExprKind::Name) ? $this->get('arguments') : $this->subExpressions();
        $read = array_merge([], ...array_map(static fn (self $part): array => $part->dataNames(), $parts));

        return array_values(array_diff($read, $this->boundNames()));
    }

    /**
     * The names a comprehension binds for itself — `h` in `[asdict(h) for h in self.lines]` — none for
     * any other expression.
     *
     * @return list<string>
     */
    private function boundNames(): array
    {
        $clauses = $this->kind === ExprKind::Comprehension ? $this->get('clauses') : [];
        $targets = array_merge([], ...array_map(static fn (self $clause): array => $clause->get('target')->flatten(), $clauses));

        return array_values(array_map(
            static fn (self $name): string => (string) $name->get('name'),
            array_filter($targets, static fn (self $target): bool => $target->is(ExprKind::Name)),
        ));
    }

    /**
     * Is every key of this dict display a string that could name a field — `total`, `tax` — rather than
     * data from outside, like a header's `Content-Type`?
     */
    public function hasFieldNameKeys(): bool
    {
        $keys = $this->kind === ExprKind::Dict ? array_filter($this->get('keys')) : [];

        return array_all($keys, static fn (self $key): bool => $key->literalType() === LiteralType::String && preg_match('/^[A-Za-z_]\w*$/', (string) $key->get('value')) === 1);
    }

    /**
     * The alternatives of a `case` pattern — `'a' | 'b'` as its two sides, any other pattern as itself.
     *
     * @return list<self>
     */
    public function alternatives(): array
    {
        return $this->kind === ExprKind::Binary && $this->get('op') === '|'
            ? [...$this->get('left')->alternatives(), ...$this->get('right')->alternatives()]
            : [$this];
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
            return $this->isNegation() && $this->get('operand')->dottedName() === $dotted;
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
     * Which of $fields — a constructor's, in order — this call hands the blank string, each argument
     * matched to its field by keyword or by position.
     *
     * @param  list<string>  $fields
     * @return list<string>
     */
    public function fieldsHandedBlank(array $fields): array
    {
        $blank = [];
        $position = 0;

        foreach ($this->isCall() ? $this->get('arguments') : [] as $argument) {
            $field = $argument->is(ExprKind::Keyword) ? (string) $argument->get('name') : ($fields[$position++] ?? null);

            if ($field !== null && $argument->argumentValue()->isBlankString()) {
                $blank[] = $field;
            }
        }

        return $blank;
    }

    /**
     * Is this `json.loads(…)` or `json.load(…)` — text from outside decoded into bare dicts and lists?
     */
    public function isJsonDecode(): bool
    {
        return $this->isCall() && in_array($this->get('callee')->dottedName(), ['json.loads', 'json.load'], true);
    }

    /**
     * Does this decode what it has just encoded — `json.loads(json.dumps(x))` — our own value round-tripped,
     * with nothing crossing a boundary?
     */
    public function decodesItsOwnEncoding(): bool
    {
        $arguments = $this->isJsonDecode() ? $this->get('arguments') : [];

        return $arguments !== [] && $arguments[0]->isCall() && $arguments[0]->get('callee')->dottedName() === 'json.dumps';
    }

    /**
     * Is this a tuple of three or more elements read from at least two different names — a bundle whose
     * slots mean different things and are known only by their position?
     */
    public function isPositionalTuple(): bool
    {
        $elements = $this->kind === ExprKind::Tuple ? $this->get('elements') : [];
        $roots = array_filter(array_map(static fn (self $element): string => $element->rootName(), $elements));

        return count($elements) >= 3
            && ! array_any($elements, static fn (self $element): bool => $element->is(ExprKind::Starred))
            && count(array_unique($roots)) >= 2;
    }

    /**
     * Does this annotation declare a sequence of one kind — `tuple[int, ...]`, `list[str]`,
     * `Sequence[Order]` — where order is the whole meaning and no position carries a name?
     */
    public function isSequenceType(): bool
    {
        $named = $this->kind === ExprKind::Subscript ? $this->get('object')->dottedName() : $this->dottedName();

        if (in_array($named, ['tuple', 'Tuple', 'typing.Tuple'], true)) {
            $index = $this->kind === ExprKind::Subscript ? $this->get('index') : null;
            $last = $index?->is(ExprKind::Tuple) === true ? array_last($index->get('elements')) : null;

            return $last?->literalType() === LiteralType::Ellipsis;
        }

        return in_array($named, ['list', 'List', 'typing.List', 'Sequence', 'typing.Sequence', 'collections.abc.Sequence', 'Iterable', 'typing.Iterable', 'collections.abc.Iterable'], true);
    }

    /**
     * Does this annotation spell a class variable — `ClassVar`, `ClassVar[int]`, through `typing` or bare?
     */
    public function isClassVarType(): bool
    {
        $named = $this->kind === ExprKind::Subscript ? $this->get('object') : $this;

        return in_array($named->dottedName(), ['ClassVar', 'typing.ClassVar'], true);
    }

    /**
     * Does this annotation spell a callable that may be `None` — `Callable[..] | None`, `None | Callable`,
     * `Optional[Callable[..]]`?
     */
    public function isOptionalCallableType(): bool
    {
        return $this->optionalOf()->isSomeAnd(static fn (self $type): bool => $type->isCallableType());
    }

    /**
     * The type this annotation makes optional — `X` in `X | None`, `None | X` and `Optional[X]` — none for
     * an annotation that admits no `None`.
     *
     * @return Option<self>
     */
    public function optionalOf(): Option
    {
        if ($this->kind === ExprKind::Subscript && in_array($this->get('object')->dottedName(), ['Optional', 'typing.Optional'], true)) {
            return Option::some($this->get('index'));
        }

        if ($this->kind !== ExprKind::Binary || $this->get('op') !== '|') {
            return Option::none();
        }

        return self::besideNone($this->get('left'), $this->get('right'));
    }

    /**
     * Is this a value sitting there — a literal or a bare name — rather than work that computes one?
     */
    public function isBareValue(): bool
    {
        return $this->literalType() !== null || $this->kind === ExprKind::Name;
    }

    /**
     * Does this ask only whether $name is set — `name` or `not name`? Anything richer is reasoning, not
     * obeying a flag.
     */
    public function testsFlag(string $name): bool
    {
        $tested = $this->isNegation() ? $this->get('operand') : $this;

        return $tested->kind === ExprKind::Name && $tested->get('name') === $name;
    }

    /**
     * Is this `a if test else b` on a test $tests accepts, with work on both sides rather than two
     * constants — a choice between behaviours, not a mapping to values?
     *
     * @param  Closure(self): bool  $tests
     */
    public function choosesBetweenWork(Closure $tests): bool
    {
        return $this->kind === ExprKind::Conditional
            && ! $this->get('then')->isBareValue()
            && ! $this->get('else')->isBareValue()
            && $tests($this->get('test'));
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
     * The enum whose members an `or` chain tests one subject against — `Status` in
     * `x == Status.A or x == Status.B` (or with `is`) — when every link is such a test of the same
     * subject against a member of the same one of $enums, two or more of them.
     *
     * @return Option<string>
     */
    public function orChainedCaseClass(Enums $enums): Option
    {
        $links = $this->orLinks();
        $tests = array_map(static fn (self $link): ?array => $link->caseTest($enums), $links);

        if (count($links) < 2 || in_array(null, $tests, true)) {
            return Option::none();
        }

        [$subject, $class] = $tests[0];
        $alike = array_all($tests, static fn (array $test): bool => $test[1] === $class && $test[0]->isSame($subject));

        return $alike ? Option::some($class) : Option::none();
    }

    /**
     * The operands of an `or` chain, flattened — this one alone when it is not an `or`.
     *
     * @return list<self>
     */
    private function orLinks(): array
    {
        return $this->isOr()
            ? [...$this->get('left')->orLinks(), ...$this->get('right')->orLinks()]
            : [$this];
    }

    /**
     * A `==` or `is` test of a subject against a member of one of $enums, as the subject and the enum.
     *
     * @return array{self, string}|null
     */
    private function caseTest(Enums $enums): ?array
    {
        if ($this->kind !== ExprKind::Compare || ! in_array($this->get('operators'), [['=='], ['is']], true)) {
            return null;
        }

        [$left, $right] = $this->get('operands');

        return match (true) {
            $right->isMemberOf($enums) => [$left, $right->get('object')->dottedName()],
            $left->isMemberOf($enums) => [$right, $left->get('object')->dottedName()],
            default => null,
        };
    }

    /**
     * Is this `Class.MEMBER` of one of $enums?
     */
    private function isMemberOf(Enums $enums): bool
    {
        return $this->kind === ExprKind::Attribute && $this->get('object')->is(ExprKind::Name) && $enums->isEnum($this->get('object')->dottedName());
    }

    /**
     * Is this an `or`?
     */
    public function isOr(): bool
    {
        return $this->kind === ExprKind::Binary && $this->get('op') === 'or';
    }

    /**
     * The keyword arguments this call passes — `meta=…` in `copy_with(meta=…)` — none for anything else.
     *
     * @return list<self>
     */
    public function keywordArguments(): array
    {
        return $this->isCall() ? array_values(array_filter($this->get('arguments'), static fn (self $argument): bool => $argument->kind === ExprKind::Keyword)) : [];
    }

    /**
     * Does this build a value — a method call (`Payload.of(x)`, `p.to_dict()`), or a dict, list or set
     * written out — rather than pass one along? A bare `f(x)` may be any function, `_("text")` included,
     * so it is not counted as building anything.
     */
    public function isConstruction(): bool
    {
        return ($this->isCall() && $this->get('callee')->is(ExprKind::Attribute)) || in_array($this->kind, [ExprKind::Dict, ExprKind::List, ExprKind::Set], true);
    }

    /**
     * The skeleton of how this value is built, blind to names and literals — `Payload(port=1).to_dict()` and
     * `Other().to_dict()` share `name().to_dict()`, and a dict literal is `dict` whatever it holds.
     */
    public function constructionShape(): string
    {
        return match ($this->kind) {
            ExprKind::Call => $this->get('callee')->constructionShape() . '()',
            ExprKind::Attribute => $this->get('object')->constructionShape() . '.' . $this->get('name'),
            default => $this->kind->value,
        };
    }

    /**
     * Is this `"\n".join(...)` — lines put together into one text?
     */
    public function isNewlineJoin(): bool
    {
        if (! $this->isCall() || ! $this->get('callee')->is(ExprKind::Attribute) || $this->get('callee')->get('name') !== 'join') {
            return false;
        }

        $separator = $this->get('callee')->get('object');

        return $separator->literalType() === LiteralType::String && $separator->get('value') === '\n';
    }

    /**
     * The lines this join is handed written out — the elements of a list or tuple literal passed to it — none
     * when what it joins is computed.
     *
     * @return list<self>
     */
    public function joinedLines(): array
    {
        $joined = $this->isCall() ? ($this->get('arguments')[0] ?? null) : null;

        return $joined !== null && in_array($joined->kind, [ExprKind::List, ExprKind::Tuple], true) ? $joined->get('elements') : [];
    }

    /**
     * Is this text written into the source — a string or an f-string — rather than a value computed?
     */
    public function isFixedText(): bool
    {
        return $this->literalType() === LiteralType::String || $this->kind === ExprKind::FString;
    }

    /**
     * Does this reach into $inner as though it were there — `inner.x`, `inner()`, `inner[k]`?
     */
    public function dereferences(self $inner): bool
    {
        if ($this->kind === ExprKind::Call) {
            return $this->get('callee') === $inner;
        }

        return in_array($this->kind, [ExprKind::Attribute, ExprKind::Subscript], true) && $this->get('object') === $inner;
    }

    /**
     * Does this admit that $inner may be missing — `inner is None`, `not inner`, `inner or d`, `inner and x`?
     */
    public function acknowledgesAbsenceOf(self $inner): bool
    {
        if ($this->noneTestedOperand()->isSomeAnd(static fn (self $operand): bool => $operand === $inner)) {
            return true;
        }

        return $this->isNegation() || $this->isShortCircuit();
    }

    /**
     * The attribute this reads off `self` — `total` in `self.total` — or empty for anything else.
     */
    public function selfAttribute(): string
    {
        return $this->isOwnAttributeRead() ? (string) $this->get('name') : '';
    }

    /**
     * What this compares with `None` — `x` in `x is None` and `x is not None`.
     *
     * @return Option<self>
     */
    public function noneTestedOperand(): Option
    {
        if ($this->kind !== ExprKind::Compare || ! in_array($this->get('operators'), [['is'], ['is not']], true)) {
            return Option::none();
        }

        return self::besideNone(...$this->get('operands'));
    }

    /**
     * Whichever of $left and $right is not the literal `None`, when the other one is.
     *
     * @return Option<self>
     */
    private static function besideNone(self $left, self $right): Option
    {
        if ($right->literalType() === LiteralType::None) {
            return Option::some($left);
        }

        return $left->literalType() === LiteralType::None ? Option::some($right) : Option::none();
    }

    /**
     * Is this `not x`?
     */
    public function isNegation(): bool
    {
        return $this->kind === ExprKind::Unary && $this->get('op') === 'not';
    }

    /**
     * Is this an `and`?
     */
    public function isAnd(): bool
    {
        return $this->kind === ExprKind::Binary && $this->get('op') === 'and';
    }

    /**
     * The conditions this `and` joins, however it nests — `a`, `b`, `c` from `a and b and c`; itself for
     * anything else.
     *
     * @return list<self>
     */
    public function conjuncts(): array
    {
        return $this->isAnd() ? [...$this->get('left')->conjuncts(), ...$this->get('right')->conjuncts()] : [$this];
    }

    /**
     * Is this `isinstance(subject, Cls)` — one value tested against ONE class? A tuple of classes is a
     * membership question, not an arm of a switch.
     */
    public function isTypeTest(): bool
    {
        $arguments = $this->isInstanceCheck() ? $this->get('arguments') : [];

        return count($arguments) === 2 && $arguments[1]->dottedName() !== '';
    }

    /**
     * Is this an `isinstance(...)` check?
     */
    public function isInstanceCheck(): bool
    {
        return $this->isCall() && $this->get('callee')->dottedName() === 'isinstance';
    }

    /**
     * How many attribute reaches this holds — one in `order.paid`, two in `order.owner.name` — the substance
     * a condition carries.
     */
    public function reachCount(): int
    {
        return count(array_filter($this->flatten(), static fn (self $part): bool => $part->kind === ExprKind::Attribute));
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
     * The name this expression is a projection of — `order` in `order.customer.email` and `order.total()` —
     * empty for anything that is no plain read: a call with arguments, a subscript, a bare name.
     */
    public function projectionRoot(): string
    {
        return match ($this->kind) {
            ExprKind::Attribute => $this->get('object')->is(ExprKind::Name) ? (string) $this->get('object')->get('name') : $this->get('object')->projectionRoot(),
            ExprKind::Call => $this->get('arguments') === [] && $this->get('callee')->is(ExprKind::Attribute) ? $this->get('callee')->projectionRoot() : '',
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
        if ($this->isOr()) {
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
