<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cs;

use JesseGall\CodeCommandments\Positioned;
use JesseGall\CodeCommandments\Support\Prose;
use JesseGall\CodeCommandments\SyntaxExpression;
use JesseGall\CodeCommandments\SyntaxNode;
use JesseGall\PhpTypes\Option;

/**
 * One node of a C# syntax tree as the Roslyn bridge wrote it — its kind by Roslyn's own name, the part
 * it plays (statement, expression, member, type), its span, and what the compiler resolved about it.
 * One class for every kind: a statement answers {@see SyntaxNode}, an expression {@see SyntaxExpression},
 * so the readings written for the other engines read C# the same way.
 */
final class Node implements SyntaxNode, SyntaxExpression
{
    use Positioned;

    /**
     * The kinds that run a body of their own.
     */
    private const array FUNCTIONS = [
        'MethodDeclaration', 'ConstructorDeclaration', 'DestructorDeclaration', 'OperatorDeclaration', 'ConversionOperatorDeclaration',
        'PropertyDeclaration', 'IndexerDeclaration',
        'LocalFunctionStatement', 'GetAccessorDeclaration', 'SetAccessorDeclaration', 'InitAccessorDeclaration',
        'AddAccessorDeclaration', 'RemoveAccessorDeclaration', 'ParenthesizedLambdaExpression', 'SimpleLambdaExpression', 'AnonymousMethodExpression',
    ];

    /**
     * The dictionary types a lookup reads, by the name the compiler gives them without their arguments.
     */
    private const array DICTIONARIES = [
        'global::System.Collections.Generic.Dictionary<', 'global::System.Collections.Generic.IDictionary<',
        'global::System.Collections.Generic.IReadOnlyDictionary<', 'global::System.Collections.Concurrent.ConcurrentDictionary<',
        'global::System.Collections.Immutable.ImmutableDictionary<', 'global::System.Collections.Immutable.IImmutableDictionary<',
        'global::System.Collections.Frozen.FrozenDictionary<',
    ];

    /**
     * The JSON object types read by a string indexer.
     */
    private const array JSON_OBJECTS = ['global::System.Text.Json.Nodes.JsonNode', 'global::System.Text.Json.Nodes.JsonObject'];

    /**
     * The value types a clump is made of — what a parameter holds when it carries one datum, not an object.
     */
    private const array SCALARS = [
        'global::System.String', 'global::System.Int16', 'global::System.Int32', 'global::System.Int64', 'global::System.Decimal',
        'global::System.Double', 'global::System.Single', 'global::System.Boolean', 'global::System.Char', 'global::System.Byte',
        'global::System.DateTime', 'global::System.DateTimeOffset', 'global::System.DateOnly', 'global::System.TimeOnly',
        'global::System.TimeSpan', 'global::System.Guid',
    ];

    /**
     * The string type, by the name the compiler gives it.
     */
    private const string STRING = 'global::System.String';

    /**
     * The exceptions that name no failure a caller could catch by meaning.
     */
    private const array GENERIC_EXCEPTIONS = ['global::System.Exception', 'global::System.SystemException', 'global::System.ApplicationException', 'global::System.InvalidOperationException'];

    /**
     * What an expression is made of, by name — what the shared readings walk. A call names what it calls
     * (`callee`) and a member access the member it reads (`member`, by name), so both survive where a
     * reading blanks the names a body chose for itself.
     *
     * @var array<string, mixed>
     */
    public array $props {
        get => array_filter([...$this->parts(), 'name' => $this->name, 'text' => $this->text, 'operator' => $this->operator], static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /**
     * @param  list<Node>  $children
     * @param  list<string>  $modifiers
     */
    private function __construct(
        public readonly string $kind,
        public readonly string $role,
        public readonly array $children,
        public readonly ?string $name,
        public readonly ?string $text,
        public readonly ?string $operator,
        public readonly array $modifiers,
        public readonly ?ResolvedType $type,
        public readonly ?CallTarget $target,
        public readonly ?string $symbol,
        public readonly bool $inherited,
        public readonly bool $constant,
        public readonly bool $forgivesNull,
        public readonly bool $step,
    ) {}

    /**
     * @param  array<string, mixed>  $written  a node as the bridge's contract writes it
     */
    public static function fromBridge(array $written, Vocabulary $vocabulary = new Vocabulary()): self
    {
        $target = $written['target'] ?? null;

        return new self(
            kind: $vocabulary->word((string) $written['kind']),
            role: $vocabulary->word((string) $written['role']),
            children: array_map(static fn (array $child): self => self::fromBridge($child, $vocabulary), $written['children'] ?? []),
            name: $vocabulary->maybe($written['name'] ?? null),
            text: $written['text'] ?? null,
            operator: $vocabulary->maybe($written['operator'] ?? null),
            modifiers: $vocabulary->words($written['modifiers'] ?? []),
            type: isset($written['type']) ? $vocabulary->type($written['type'], $written['nullable'], array_key_exists('value', $written), $written['inner'] ?? []) : null,
            target: is_array($target) ? $vocabulary->target($target['type'], $target['name'], $target['parameters'] ?? []) : null,
            symbol: $vocabulary->maybe($written['symbol'] ?? null),
            inherited: array_key_exists('inherited', $written),
            constant: array_key_exists('constant', $written),
            forgivesNull: array_key_exists('forgivesNull', $written),
            step: array_key_exists('step', $written),
        )->locatedAt((int) $written['start'], (int) $written['end']);
    }

    public function is(string ...$kinds): bool
    {
        return in_array($this->kind, $kinds, true);
    }

    public function isExpression(): bool
    {
        return $this->role === 'expression';
    }

    /**
     * Does this statement open a choice the reader holds open — an `if`, a loop, a `switch`? A `try`, a
     * `using` or a `lock` is a boundary, not a choice.
     */
    public function isBranchingConstruct(): bool
    {
        return $this->is('IfStatement', 'ForStatement', 'ForEachStatement', 'ForEachVariableStatement', 'WhileStatement', 'DoStatement', 'SwitchStatement');
    }

    /**
     * Does this `catch` catch everything — no type, or `Exception` itself — with no `when` filter
     * saying which failure it means?
     */
    public function isBroadCatch(): bool
    {
        if (! $this->is('CatchClause') || array_any($this->children, static fn (self $child): bool => $child->is('CatchFilterClause'))) {
            return false;
        }

        $declaration = array_values(array_filter($this->children, static fn (self $child): bool => $child->is('CatchDeclaration')))[0] ?? null;

        return $declaration === null || $declaration->type?->name === 'global::System.Exception';
    }

    /**
     * Does this `catch` make the failure vanish — an empty body, a `continue`, or a `return` of nothing
     * or of a value that says "nothing"?
     */
    public function swallows(): bool
    {
        $body = array_values(array_filter($this->children, static fn (self $child): bool => $child->is('Block')))[0] ?? null;
        $statements = $body?->children() ?? [];

        if ($body === null || count($statements) > 1) {
            return false;
        }

        $only = $statements[0] ?? null;

        return match (true) {
            $only === null, $only->is('ContinueStatement') => true,
            $only->is('ReturnStatement') => $only->returnedValue()->isNoneOr(static fn (self $value): bool => $value->isAbsenceValue()),
            default => false,
        };
    }

    /**
     * Is this a value that says "nothing" — `null`, `default`, `false`, `""`, an empty collection
     * expression, or `Array.Empty<T>()` / `Enumerable.Empty<T>()`? What a swallowed failure hands back
     * instead of itself.
     */
    public function isAbsenceValue(): bool
    {
        return match (true) {
            $this->is('NullLiteralExpression', 'DefaultLiteralExpression', 'DefaultExpression', 'FalseLiteralExpression') => true,
            $this->is('StringLiteralExpression') => $this->text === '',
            $this->is('CollectionExpression') => $this->children === [],
            $this->isCall() => $this->target?->name === 'Empty' && in_array($this->target->type, ['global::System.Array', 'global::System.Linq.Enumerable'], true),
            default => false,
        };
    }

    /**
     * Does this `throw` build an exception that names no failure — `Exception`, `SystemException`,
     * `ApplicationException`, `InvalidOperationException` — and describe it in a message written at the
     * throw? The type is the constructor the compiler resolved, never the spelling.
     */
    public function isGenericThrowWithMessage(): bool
    {
        $created = $this->is('ThrowStatement', 'ThrowExpression') ? ($this->expressions()[0] ?? null) : null;

        if ($created === null || ! $created->is('ObjectCreationExpression', 'ImplicitObjectCreationExpression')) {
            return false;
        }

        $arguments = array_values(array_filter($created->children, static fn (self $child): bool => $child->is('ArgumentList')))[0] ?? null;

        return in_array($created->target?->type, self::GENERIC_EXCEPTIONS, true) && ($arguments?->children ?? []) !== [];
    }

    /**
     * Is this a value that says "nothing" in a slot a type demands — `""`, `string.Empty`, `0`, `false`?
     * What an invented default fills a missing value with.
     */
    public function isEmptyScalar(): bool
    {
        return match (true) {
            $this->is('StringLiteralExpression') => $this->text === '',
            $this->is('NumericLiteralExpression') => $this->text === '0',
            $this->is('FalseLiteralExpression') => true,
            $this->is('SimpleMemberAccessExpression') => $this->type?->name === 'global::System.String' && $this->children[1]->name === 'Empty',
            default => false,
        };
    }

    /**
     * Is this a parameter or property defaulted to a blank string — `string note = ""`, `= string.Empty`?
     */
    public function isBlankStringDefault(): bool
    {
        return $this->is('Parameter', 'PropertyDeclaration') && array_any(
            $this->children,
            static fn (self $child): bool => $child->is('EqualsValueClause') && ($child->children[0] ?? null)?->isBlankString() === true,
        );
    }

    /**
     * Does this expression ask whether $name is blank — `name == ""`, `name != string.Empty`, `name is ""`,
     * `name.Length == 0`, or `string.IsNullOrEmpty(name)` and its whitespace twin?
     */
    public function testsBlanknessOf(string $name): bool
    {
        if ($this->is('InvocationExpression')) {
            return $this->target?->type === self::STRING
                && in_array($this->target->name, ['IsNullOrEmpty', 'IsNullOrWhiteSpace'], true)
                && array_any($this->arguments(), static fn (self $argument): bool => $argument->names($name));
        }

        if ($this->is('IsPatternExpression')) {
            return $this->children[0]->names($name) && ($this->children[1]->children[0] ?? null)?->isBlankString() === true;
        }

        if (! $this->is('EqualsExpression', 'NotEqualsExpression')) {
            return false;
        }

        [$left, $right] = $this->children;

        return ($left->names($name) && $right->isBlankString()) || ($right->names($name) && $left->isBlankString())
            || ($left->isLengthOf($name) && $right->text === '0') || ($right->isLengthOf($name) && $left->text === '0');
    }

    /**
     * Is this the blank string — `""` or `string.Empty`?
     */
    public function isBlankString(): bool
    {
        return $this->type?->name === self::STRING && $this->isEmptyScalar();
    }

    /**
     * Does this expression read $name — the bare name, or `this.` it?
     */
    private function names(string $name): bool
    {
        return ($this->is('IdentifierName') && $this->name === $name)
            || ($this->is('SimpleMemberAccessExpression') && $this->children[0]->is('ThisExpression') && $this->children[1]->name === $name);
    }

    /**
     * Is this `$name.Length`?
     */
    private function isLengthOf(string $name): bool
    {
        return $this->is('SimpleMemberAccessExpression') && $this->children[0]->names($name) && $this->children[1]->name === 'Length';
    }

    /**
     * This expression with any parentheses around it taken off — `(a ? b : c)` read as `a ? b : c`.
     */
    public function withoutParentheses(): self
    {
        return $this->is('ParenthesizedExpression') ? $this->children[0]->withoutParentheses() : $this;
    }

    /**
     * Is this a conditional expression with another conditional as one of its branches — `a ? b : c ? d : e`?
     */
    public function isNestedConditional(): bool
    {
        return $this->is('ConditionalExpression') && array_any(
            array_slice($this->children, 1),
            static fn (self $branch): bool => $branch->withoutParentheses()->is('ConditionalExpression'),
        );
    }

    /**
     * Is this an empty collection written out — `[]`, `Enumerable.Empty<T>()`, `Array.Empty<T>()`, or a
     * `new List<T>()` with nothing in it?
     */
    public function isEmptyCollection(): bool
    {
        return match (true) {
            $this->is('CollectionExpression') => $this->expressions() === [] && ! array_any($this->children, static fn (self $child): bool => $child->is('ExpressionElement', 'SpreadElement')),
            $this->is('InvocationExpression') => $this->target?->name === 'Empty' && in_array($this->target->type, ['global::System.Linq.Enumerable', 'global::System.Array'], true),
            $this->is('ObjectCreationExpression') => $this->arguments() === [] && ! array_any($this->children, static fn (self $child): bool => str_ends_with($child->kind, 'InitializerExpression')),
            default => false,
        };
    }

    /**
     * Is this literal the same value as $other — the same literal written again, or two spellings of one
     * empty value, `""` and `string.Empty`?
     */
    public function isSameValueAs(self $other): bool
    {
        if ($this->isEmptyScalar() && $other->isEmptyScalar()) {
            return $this->type?->name === $other->type?->name;
        }

        return $this->isLiteral() && $this->kind === $other->kind && $this->text === $other->text;
    }

    /**
     * What this expression falls back to when its value is missing — the right side of `x ?? fallback`,
     * or the branch a null test (`x is null ? fallback : x`, `x != null ? x : fallback`) takes on a miss.
     *
     * @return Option<self>
     */
    public function fallback(): Option
    {
        if ($this->is('CoalesceExpression')) {
            return Option::some($this->expressions()[1]);
        }

        if (! $this->is('ConditionalExpression')) {
            return Option::none();
        }

        [$test, $whenTrue, $whenFalse] = $this->expressions();

        return match (true) {
            $test->isNullTest() => Option::some($whenTrue),
            $test->isNotNullTest() => Option::some($whenFalse),
            default => Option::none(),
        };
    }

    /**
     * Does this read a dictionary by key — `TryGetValue`, `GetValueOrDefault`, or its indexer — as the
     * compiler resolved the receiver?
     */
    public function isLookup(): bool
    {
        if ($this->is('ElementAccessExpression')) {
            return self::isDictionary($this->children[0]->type?->name);
        }

        return $this->isCall() && in_array($this->target?->name, ['TryGetValue', 'GetValueOrDefault'], true) && self::isDictionary($this->target->type);
    }

    /**
     * The argument expressions this call, object creation or indexer is handed, in order.
     *
     * @return list<self>
     */
    public function arguments(): array
    {
        $list = array_values(array_filter($this->children, static fn (self $child): bool => $child->is('ArgumentList', 'BracketedArgumentList')))[0] ?? null;

        return array_values(array_filter(array_map(static fn (self $argument): ?self => $argument->expressions()[0] ?? null, $list?->children ?? [])));
    }

    /**
     * Does this read a dictionary or a JSON object by a string written in the source — `row["sku"]`,
     * `settings.TryGetValue("timeout", out …)`, `json.GetProperty("name")`? The receiver and the
     * method are the ones the compiler resolved.
     */
    public function isStringKeyRead(): bool
    {
        $key = $this->arguments()[0] ?? null;

        if ($key === null || ! $key->isConstant() || $key->type?->name !== 'global::System.String') {
            return false;
        }

        return $this->isKeyedRead();
    }

    /**
     * Does this read a string-keyed dictionary or a JSON object by its first argument — whatever that
     * argument is?
     */
    public function isKeyedRead(): bool
    {
        return match (true) {
            $this->is('ElementAccessExpression') => self::isStringKeyed($this->children[0]->type?->name),
            $this->isCall() && $this->target?->name === 'GetProperty' => $this->target->type === 'global::System.Text.Json.JsonElement',
            $this->isCall() && in_array($this->target?->name, ['TryGetValue', 'GetValueOrDefault'], true) => self::isStringKeyed($this->target->type),
            default => false,
        };
    }

    /**
     * What a keyed read reads from — the indexed expression, or the receiver of the lookup method.
     *
     * @return Option<self>
     */
    public function keyedReceiver(): Option
    {
        if ($this->is('ElementAccessExpression')) {
            return Option::some($this->children[0]);
        }

        $callee = $this->isCall() ? $this->children[0] : null;

        return Option::fromNullable($callee?->is('SimpleMemberAccessExpression') === true ? $callee->children[0] : null);
    }

    /**
     * Does this keyed read read a dictionary held under one of $names — a variable named there, not a
     * member reached through another object?
     *
     * @param  list<string|null>  $names
     */
    public function readsDictionaryNamed(array $names): bool
    {
        return $this->keyedReceiver()->isSomeAnd(static fn (self $receiver): bool => $receiver->is('IdentifierName') && in_array($receiver->name, $names, true));
    }

    /**
     * This member's value parameters — each `type name`, sorted — when it takes three or more; none
     * otherwise. Two members of different types with the same signature thread one clump of data.
     *
     * @return list<string>
     */
    public function valueParamSignature(): array
    {
        $list = array_values(array_filter($this->children, static fn (self $child): bool => $child->is('ParameterList')))[0] ?? null;
        $fields = [];

        foreach (array_filter($list?->children ?? [], static fn (self $parameter): bool => $parameter->type !== null) as $parameter) {
            $type = rtrim($parameter->type->name, '?');

            if (in_array($type, self::SCALARS, true)) {
                $fields[] = "{$type} {$parameter->name}";
            }
        }

        sort($fields);

        return count($fields) >= 3 ? $fields : [];
    }

    /**
     * The names this function declares for its own use — its parameters, and every local its body
     * declares.
     *
     * @return list<string>
     */
    public function ownNames(): array
    {
        $declarations = array_filter(
            [...$this->children, ...$this->descendants()],
            static fn (self $node): bool => $node->is('Parameter', 'VariableDeclarator', 'SingleVariableDesignation'),
        );

        return array_values(array_filter(array_map(static fn (self $declaration): ?string => $declaration->name, $declarations)));
    }

    /**
     * Is $type — or null where the compiler resolved none — a dictionary keyed by strings, or a JSON object?
     */
    private static function isStringKeyed(?string $type): bool
    {
        return $type !== null && (
            in_array($type, self::JSON_OBJECTS, true)
            || array_any(self::DICTIONARIES, static fn (string $dictionary): bool => str_starts_with($type, $dictionary . 'global::System.String,'))
        );
    }

    /**
     * Is this condition `x is null` or `x == null`?
     */
    private function isNullTest(): bool
    {
        return ($this->is('IsPatternExpression') && ($this->children[1] ?? null)?->is('ConstantPattern') === true && $this->children[1]->children[0]->is('NullLiteralExpression'))
            || ($this->is('EqualsExpression') && array_any($this->expressions(), static fn (self $side): bool => $side->is('NullLiteralExpression')));
    }

    /**
     * Is this condition `x is not null` or `x != null`?
     */
    private function isNotNullTest(): bool
    {
        return ($this->is('IsPatternExpression') && ($this->children[1] ?? null)?->is('NotPattern') === true)
            || ($this->is('NotEqualsExpression') && array_any($this->expressions(), static fn (self $side): bool => $side->is('NullLiteralExpression')));
    }

    /**
     * Is $type — as the compiler named it, or null where it resolved none — a dictionary type?
     */
    private static function isDictionary(?string $type): bool
    {
        return $type !== null && array_any(self::DICTIONARIES, static fn (string $dictionary): bool => str_starts_with($type, $dictionary));
    }

    /**
     * Is this a loop — `for`, `foreach`, `while`, `do`?
     */
    public function isLoop(): bool
    {
        return $this->is('ForStatement', 'ForEachStatement', 'ForEachVariableStatement', 'WhileStatement', 'DoStatement');
    }

    /**
     * Is this a `for` whose step moves no counter — it assigns the next item instead, as in
     * `for (var link = head; link != null; link = link.Next)`? A `for` with no step at all is not one.
     */
    public function isNonCountingFor(): bool
    {
        $steps = array_filter($this->children, static fn (self $child): bool => $child->step);

        return $this->is('ForStatement')
            && $steps !== []
            && ! array_any($steps, static fn (self $step): bool => $step->advancesACounter());
    }

    /**
     * Does this expression move a counter along — `i++`, `++i`, `i--`, `--i`, `i += n`, `i -= n`, an
     * assignment of one of those (`row[i] = i++`), or a step by a fixed amount (`date = date.PlusDays(1)`)?
     */
    public function advancesACounter(): bool
    {
        if ($this->is('PostIncrementExpression', 'PreIncrementExpression', 'PostDecrementExpression', 'PreDecrementExpression', 'AddAssignmentExpression', 'SubtractAssignmentExpression')) {
            return true;
        }

        if (! $this->is('SimpleAssignmentExpression')) {
            return false;
        }

        [$target, $value] = $this->children;

        return $value->advancesACounter() || $value->isFixedStepFrom($target);
    }

    /**
     * Is this a call on $start that is handed only constants — `date.PlusDays(1)`, the next value a fixed
     * distance on from $start?
     */
    private function isFixedStepFrom(self $start): bool
    {
        $arguments = $this->arguments();

        return $this->isCall()
            && $start->is('IdentifierName')
            && $this->children[0]->is('SimpleMemberAccessExpression')
            && $this->children[0]->children[0]->names((string) $start->name)
            && $arguments !== []
            && array_all($arguments, static fn (self $argument): bool => $argument->isConstant());
    }

    /**
     * Is this a class of two or more `const` fields, each a one-line string or number written in the
     * source, and nothing else — a closed set spelled out as constants? A multi-line string is a document
     * kept on a shelf, not a case anything dispatches on.
     */
    public function isConstClassEnum(): bool
    {
        $members = array_filter($this->children, static fn (self $child): bool => $child->role === 'member');
        $values = array_merge([], ...array_map(static fn (self $member): array => $member->constantValues(), $members));

        return $this->is('ClassDeclaration')
            && $members !== []
            && array_all($members, static fn (self $member): bool => $member->is('FieldDeclaration') && $member->hasModifier('const'))
            && count($values) >= 2
            && array_all($values, static fn (self $value): bool => $value->isCaseLiteral());
    }

    /**
     * The values this field declaration's declarators are given.
     *
     * @return list<self>
     */
    private function constantValues(): array
    {
        $clauses = array_filter($this->descendants(), static fn (self $node): bool => $node->is('EqualsValueClause'));

        return array_values(array_map(static fn (self $clause): self => $clause->children[0], $clauses));
    }

    /**
     * Is this a literal that could name a case — a number, or a string on one line?
     */
    private function isCaseLiteral(): bool
    {
        return $this->is('NumericLiteralExpression')
            || ($this->is('StringLiteralExpression') && ! str_contains((string) $this->text, "\n"));
    }

    /**
     * The enums this `||` chain or `or` pattern tests two or more different cases of — `Status` in
     * `s == Status.Paid || s == Status.Refunded` and in `s is Status.Paid or Status.Refunded`.
     *
     * @return list<string>
     */
    public function enumsTestedAsAGroup(): array
    {
        $cases = array_merge([], ...array_map(static fn (self $operand): array => $operand->testedEnumCase(), $this->orOperands()));
        $casesByEnum = [];

        foreach ($cases as $case) {
            $casesByEnum[(string) $case->type?->name][(string) $case->children[1]->name] = true;
        }

        return array_keys(array_filter($casesByEnum, static fn (array $names): bool => count($names) >= 2));
    }

    /**
     * The sides of this `||` chain or `or` pattern, the nested ones of the same kind unrolled.
     *
     * @return list<self>
     */
    private function orOperands(): array
    {
        $kind = $this->kind;

        return array_merge([], ...array_map(static fn (self $side): array => $side->kind === $kind ? $side->orOperands() : [$side], $this->children));
    }

    /**
     * The enum case this operand tests for — the member in `s == Status.Paid` or in the pattern
     * `Status.Paid` — none for anything else.
     *
     * @return list<self>
     */
    private function testedEnumCase(): array
    {
        $sides = match (true) {
            $this->is('EqualsExpression', 'ConstantPattern') => $this->children,
            default => [],
        };

        return array_values(array_filter($sides, static fn (self $side): bool => $side->isEnumCase()));
    }

    /**
     * Is this an enum case named through its enum — `Status.Paid`, a constant of the very type it is read from?
     */
    public function isEnumCase(): bool
    {
        return $this->is('SimpleMemberAccessExpression')
            && $this->constant
            && $this->type !== null
            && $this->type->name === $this->children[0]->type?->name;
    }

    /**
     * The strings this tests a value's membership in, when it tests it against nothing but strings written
     * right there — `"paid"` and `"late"` in `new[] { "paid", "late" }.Contains(status)` and in
     * `status is "paid" or "late"`; none for anything else.
     *
     * @return list<string>
     */
    public function membershipLiterals(): array
    {
        $elements = match (true) {
            $this->is('IsPatternExpression') && $this->children[1]->is('OrPattern') => array_map(static fn (self $operand): self => $operand->is('ConstantPattern') ? $operand->children[0] : $operand, $this->children[1]->orOperands()),
            $this->isCall() && $this->target?->name === 'Contains' && $this->children[0]->is('SimpleMemberAccessExpression') => $this->children[0]->children[0]->withoutParentheses()->writtenElements(),
            default => [],
        };

        return array_all($elements, static fn (self $element): bool => $element->is('StringLiteralExpression'))
            ? array_map(static fn (self $element): string => (string) $element->text, $elements)
            : [];
    }

    /**
     * The lines this `string.Join` puts on separate lines — its elements, written right there as an array, a
     * collection expression or its `params`, when the separator is a newline (`"\n"`, `"\r\n"`,
     * `Environment.NewLine`); none for any other call.
     *
     * @return list<self>
     */
    public function joinedLines(): array
    {
        $arguments = $this->arguments();

        if (! $this->isCall() || $this->target?->type !== self::STRING || $this->target->name !== 'Join' || ! ($arguments[0] ?? null)?->isNewline()) {
            return [];
        }

        return count($arguments) === 2 ? $arguments[1]->withoutParentheses()->writtenElements() : array_slice($arguments, 1);
    }

    /**
     * Is this a line break — `"\n"`, `"\r\n"`, or `Environment.NewLine`?
     */
    private function isNewline(): bool
    {
        return ($this->is('StringLiteralExpression') && in_array($this->text, ["\n", "\r\n"], true))
            || ($this->is('SimpleMemberAccessExpression') && $this->children[1]->name === 'NewLine' && $this->type?->name === self::STRING);
    }

    /**
     * Is this statement `builder.AppendLine(…)` on a `StringBuilder` — one line of a text written line by line?
     */
    public function isAppendLine(): bool
    {
        $call = $this->is('ExpressionStatement') ? $this->children[0] : null;

        return $call?->isCall() === true && $call->target?->name === 'AppendLine' && $call->target->type === 'global::System.Text.StringBuilder';
    }

    /**
     * The builder this `AppendLine` statement writes to, as a fingerprint — so a run on one builder is told from
     * a run that switches to another.
     */
    public function appendReceiver(): string
    {
        return StructuralHash::ofExpression($this->children[0]->children[0]->children[0]);
    }

    /**
     * Is the line this `AppendLine` statement writes text written into the source?
     */
    public function isAppendingFixedText(): bool
    {
        return ($this->children[0]->arguments()[0] ?? null)?->isFixedText() === true;
    }

    /**
     * Is this text written into the source — a string literal, or an interpolated string — rather than a value
     * worked out?
     */
    public function isFixedText(): bool
    {
        return $this->is('StringLiteralExpression', 'InterpolatedStringExpression');
    }

    /**
     * The elements of the collection this writes out — an array, a collection initializer or a collection
     * expression, through a cast — none for anything else.
     *
     * @return list<self>
     */
    private function writtenElements(): array
    {
        return match (true) {
            $this->is('CollectionExpression') => array_map(static fn (self $element): self => $element->children[0], $this->children),
            $this->is('CastExpression') => array_last($this->children)->withoutParentheses()->writtenElements(),
            $this->is('ArrayCreationExpression', 'ImplicitArrayCreationExpression', 'ObjectCreationExpression') => array_values(array_filter($this->children, static fn (self $child): bool => str_ends_with($child->kind, 'InitializerExpression')))[0]->children ?? [],
            default => [],
        };
    }

    /**
     * The enum members this switch names in its cases — arms and labels guarded by `when` left out, since
     * they do not match every time.
     *
     * @return list<string>
     */
    public function namedCases(): array
    {
        $tests = match (true) {
            $this->is('SwitchExpression') => array_map(static fn (self $arm): self => $arm->children[0], array_filter(array_slice($this->children, 1), static fn (self $arm): bool => ! $arm->isGuarded())),
            $this->is('SwitchStatement') => array_filter(array_merge([], ...array_map(static fn (self $section): array => $section->children, array_slice($this->children, 1))), static fn (self $child): bool => $child->is('CaseSwitchLabel', 'CasePatternSwitchLabel') && ! $child->isGuarded()),
            default => [],
        };
        $values = array_merge([], ...array_map(static fn (self $test): array => $test->outermostExpressions(), $tests));
        $cases = array_filter(array_merge([], ...array_map(static fn (self $value): array => $value->flatten(), $values)), static fn (self $node): bool => $node->isEnumCase());

        return array_values(array_unique(array_map(static fn (self $case): string => (string) $case->children[1]->name, $cases)));
    }

    /**
     * Is this a case test that only matches when its `when` clause holds — a guarded arm, or a guarded
     * `case` label?
     */
    private function isGuarded(): bool
    {
        return array_any($this->children, static fn (self $child): bool => $child->is('WhenClause'));
    }

    /**
     * What this switch hands back when no case matches — the value of its `_` arm, or what the `default:`
     * section returns — none when it has neither.
     *
     * @return Option<self>
     */
    public function fallbackValue(): Option
    {
        $fallback = match (true) {
            $this->is('SwitchExpression') => array_values(array_filter(array_slice($this->children, 1), static fn (self $arm): bool => $arm->children[0]->is('DiscardPattern')))[0] ?? null,
            $this->is('SwitchStatement') => array_values(array_filter(array_slice($this->children, 1), static fn (self $section): bool => array_any($section->children, static fn (self $label): bool => $label->is('DefaultSwitchLabel'))))[0] ?? null,
            default => null,
        };
        $answer = array_values(array_filter($fallback?->children ?? [], static fn (self $child): bool => $child->isExpression() || $child->is('ReturnStatement')))[0] ?? null;

        return Option::fromNullable($answer?->is('ReturnStatement') === true ? ($answer->children[0] ?? null) : $answer);
    }

    /**
     * The keys this dictionary is built with, when every one is a string written in the source — `sku` and
     * `qty` in `new Dictionary<string, object> { ["sku"] = …, ["qty"] = … }` — none for anything else.
     *
     * @return list<string>
     */
    public function literalKeys(): array
    {
        if (! $this->is('ObjectCreationExpression', 'ImplicitObjectCreationExpression') || ! self::isDictionary($this->type?->name)) {
            return [];
        }

        $initializers = array_filter($this->children, static fn (self $child): bool => $child->is('ObjectInitializerExpression', 'CollectionInitializerExpression'));
        $entries = array_merge([], ...array_map(static fn (self $initializer): array => $initializer->children, $initializers));
        $keys = array_merge([], ...array_map(static fn (self $entry): array => $entry->entryKey(), $entries));

        return $entries !== [] && count($keys) === count($entries) && array_all($keys, static fn (self $key): bool => $key->is('StringLiteralExpression'))
            ? array_map(static fn (self $key): string => (string) $key->text, $keys)
            : [];
    }

    /**
     * The key of this dictionary initializer entry — `"sku"` in `["sku"] = …` and in `{ "sku", … }`.
     *
     * @return list<self>
     */
    private function entryKey(): array
    {
        return match (true) {
            $this->is('SimpleAssignmentExpression') && $this->children[0]->is('ImplicitElementAccess') => array_slice($this->children[0]->arguments(), 0, 1),
            $this->is('ComplexElementInitializerExpression') => array_slice($this->children, 0, 1),
            default => [],
        };
    }

    /**
     * Does this expression write the target it names first — an assignment of any kind, or a step up or down?
     */
    public function isWrite(): bool
    {
        return str_ends_with($this->kind, 'AssignmentExpression') || $this->is('PostIncrementExpression', 'PostDecrementExpression', 'PreIncrementExpression', 'PreDecrementExpression');
    }

    /**
     * Is this a record — a class or a struct declared as a value?
     */
    public function isRecord(): bool
    {
        return $this->is('RecordDeclaration', 'RecordStructDeclaration');
    }

    /**
     * The names this type keeps its state under — its primary constructor's parameters, its properties and
     * its instance fields.
     *
     * @return list<string>
     */
    public function stateNames(): array
    {
        $parameters = array_merge([], ...array_map(static fn (self $list): array => $list->children, array_filter($this->children, static fn (self $child): bool => $child->is('ParameterList'))));
        $properties = array_filter($this->children, static fn (self $child): bool => $child->is('PropertyDeclaration'));
        $fields = array_filter($this->children, static fn (self $child): bool => $child->is('FieldDeclaration') && ! $child->hasModifier('static') && ! $child->hasModifier('const'));
        $declarators = array_filter(array_merge([], ...array_map(static fn (self $field): array => $field->descendants(), $fields)), static fn (self $node): bool => $node->is('VariableDeclarator'));

        return array_values(array_filter(array_map(static fn (self $member): ?string => $member->name, [...$parameters, ...$properties, ...$declarators])));
    }

    /**
     * Is this a plain read of one of $names — the bare name, or `this.` it?
     *
     * @param  list<string>  $names
     */
    public function readsMember(array $names): bool
    {
        return array_any($names, fn (string $name) => $this->names($name));
    }

    /**
     * Does this member declare its result as a tuple whose slots have no names and two of them share a type —
     * `(decimal, decimal, string)`, nullable or awaited (`Task<(int, int)>` on an `async` member)? Those two
     * slots can be swapped and nothing notices; slots of different types cannot be mixed up unseen.
     */
    public function returnsPositionalTuple(): bool
    {
        if (! $this->is('MethodDeclaration', 'LocalFunctionStatement', 'PropertyDeclaration')) {
            return false;
        }

        $declared = array_values(array_filter($this->children, static fn (self $child): bool => $child->role === 'type'))[0] ?? null;
        $result = $declared?->is('NullableType') === true ? $declared->children[0] : $declared;
        $generic = $result?->is('QualifiedName') === true ? array_last($result->children) : $result;
        $awaited = $this->hasModifier('async') && $generic?->is('GenericName') === true ? ($generic->children[0]->children[0] ?? null) : $result;

        if ($awaited?->is('TupleType') !== true || array_any($awaited->children, static fn (self $element): bool => $element->name !== null)) {
            return false;
        }

        $types = array_map(static fn (self $element): string => StructuralHash::ofExpression($element->children[0]), $awaited->children);

        return count(array_unique($types)) < count($types);
    }

    /**
     * The positions of the arguments this call or creation hands a blank string, counted up to its first named
     * argument, after which a position no longer says which parameter it fills.
     *
     * @return list<int>
     */
    public function blankArgumentPositions(): array
    {
        $list = array_values(array_filter($this->children, static fn (self $child): bool => $child->is('ArgumentList')))[0] ?? null;
        $positions = [];

        foreach ($list?->children ?? [] as $position => $argument) {
            if (array_any($argument->children, static fn (self $part): bool => $part->is('NameColon'))) {
                break;
            }

            if (($argument->expressions()[0] ?? null)?->isBlankString() === true) {
                $positions[] = $position;
            }
        }

        return $positions;
    }

    /**
     * The members this creation's initializer sets to a blank string — `Body` in `new Note { Body = "" }`.
     *
     * @return list<string>
     */
    public function membersInitializedBlank(): array
    {
        $initializers = array_filter($this->children, static fn (self $child): bool => $child->is('ObjectInitializerExpression'));
        $entries = array_merge([], ...array_map(static fn (self $initializer): array => $initializer->children, $initializers));
        $blank = array_filter($entries, static fn (self $entry): bool => $entry->is('SimpleAssignmentExpression') && $entry->children[0]->is('IdentifierName') && $entry->children[1]->isBlankString());

        return array_values(array_map(static fn (self $entry): string => (string) $entry->children[0]->name, $blank));
    }

    /**
     * The names this type keeps a required `string` under — a primary constructor parameter or a property
     * typed `string`, not `string?`.
     *
     * @return list<string>
     */
    public function requiredTextNames(): array
    {
        $parameters = array_merge([], ...array_map(static fn (self $list): array => $list->children, array_filter($this->children, static fn (self $child): bool => $child->is('ParameterList'))));
        $properties = array_filter($this->children, static fn (self $child): bool => $child->is('PropertyDeclaration'));
        $text = array_filter([...$parameters, ...$properties], static fn (self $member): bool => array_any($member->children, static fn (self $type): bool => $type->is('PredefinedType') && $type->name === 'string'));

        return array_values(array_filter(array_map(static fn (self $member): ?string => $member->name, $text)));
    }

    /**
     * Is this a member that holds state — a field, a constant, or a stored property (an auto-property, or one
     * given a starting value)? An abstract property holds nothing; it asks a subclass to.
     */
    public function isStateMember(): bool
    {
        return $this->is('FieldDeclaration') || ($this->is('PropertyDeclaration') && ! $this->hasModifier('abstract') && $this->isStoredProperty());
    }

    /**
     * Is this a constant — a `const` field, or a `static readonly` one: a fact about the type, not per object?
     */
    public function isConstantMember(): bool
    {
        return $this->is('FieldDeclaration') && ($this->hasModifier('const') || ($this->hasModifier('static') && $this->hasModifier('readonly')));
    }

    /**
     * Is this a member that holds per-object state — an instance field or a stored instance property?
     */
    public function isInstanceStateMember(): bool
    {
        return $this->isStateMember() && ! $this->hasModifier('static') && ! $this->hasModifier('const');
    }

    /**
     * Is this property stored rather than computed — given a starting value, or an auto-property whose
     * accessors have no bodies?
     */
    private function isStoredProperty(): bool
    {
        $accessors = array_values(array_filter($this->children, static fn (self $child): bool => $child->is('AccessorList')))[0]->children ?? [];

        return array_any($this->children, static fn (self $child): bool => $child->is('EqualsValueClause'))
            || ($accessors !== [] && array_all($accessors, static fn (self $accessor): bool => $accessor->children === []));
    }

    /**
     * Does this method or property answer a `bool` about the object alone — a property, or a method that takes
     * nothing to compare against?
     */
    public function isStatePredicate(): bool
    {
        $declared = array_values(array_filter($this->children, static fn (self $child): bool => $child->role === 'type'))[0] ?? null;
        $parameters = array_values(array_filter($this->children, static fn (self $child): bool => $child->is('ParameterList')))[0] ?? null;

        return $declared?->is('PredefinedType') === true && $declared->name === 'bool'
            && ($this->is('PropertyDeclaration') || ($this->is('MethodDeclaration') && $parameters?->children === []));
    }

    /**
     * Is this method's whole body a two-way branch on one of its own `bool` parameters — `if (flag) … else …`
     * or a returned `flag ? … : …`? Two methods sharing one name, the flag choosing between them. A choice that
     * only picks a constant is a lookup of the flag's value, not two jobs.
     */
    public function switchesOnAFlag(): bool
    {
        $parameters = array_merge([], ...array_map(static fn (self $list): array => $list->children, array_filter($this->children, static fn (self $child): bool => $child->is('ParameterList'))));
        $flags = array_filter($parameters, static fn (self $parameter): bool => $parameter->type?->name === 'global::System.Boolean');

        return $this->functionBody()->isSomeAnd(static fn (self $body): bool => $body->soleReturnedValue()?->withoutParentheses()->isValueChoice() !== true
            && $body->twoWayCondition()->isSomeAnd(static fn (self $condition): bool => array_any($flags, static fn (self $flag): bool => $condition->decidesOn((string) $flag->name))));
    }

    /**
     * Is this a conditional expression that only picks a constant — `member ? 5 : 0`, `!refused ? Pass :
     * blocking ? Block : Warn` — a lookup of what its condition says, rather than a choice between two jobs?
     */
    private function isValueChoice(): bool
    {
        return $this->is('ConditionalExpression')
            && array_all([$this->children[1], $this->children[2]], static fn (self $side): bool => $side->withoutParentheses()->isConstant() || $side->withoutParentheses()->isValueChoice());
    }

    /**
     * The condition this body is nothing but a branch on — an `if` with an `else` as its one statement, or a
     * conditional expression it returns — none for any other body.
     *
     * @return Option<self>
     */
    private function twoWayCondition(): Option
    {
        $only = $this->is('Block') && count($this->children) === 1 ? $this->children[0] : null;

        if ($only?->is('IfStatement') === true && array_any($only->children, static fn (self $part): bool => $part->is('ElseClause'))) {
            return Option::some($only->children[0]);
        }

        $returned = $this->soleReturnedValue()?->withoutParentheses();

        return Option::fromNullable($returned?->is('ConditionalExpression') === true ? $returned->children[0] : null);
    }

    /**
     * The value this body hands back as its only statement — an expression body, or a lone `return`.
     */
    private function soleReturnedValue(): ?self
    {
        return match (true) {
            $this->is('ArrowExpressionClause') => $this->children[0],
            $this->is('Block') && count($this->children) === 1 && $this->children[0]->is('ReturnStatement') => $this->children[0]->children[0] ?? null,
            default => null,
        };
    }

    /**
     * Does this condition decide on $name alone — the value itself, or its negation?
     */
    private function decidesOn(string $name): bool
    {
        $test = $this->withoutParentheses();

        return $test->names($name) || ($test->is('LogicalNotExpression') && $test->children[0]->withoutParentheses()->names($name));
    }

    /**
     * The conditions this `&&` chain joins, the nested ones unrolled and parentheses stripped — none for
     * anything but a `&&`.
     *
     * @return list<self>
     */
    public function conjuncts(): array
    {
        if (! $this->is('LogicalAndExpression')) {
            return [$this];
        }

        return array_merge([], ...array_map(static fn (self $side): array => $side->withoutParentheses()->conjuncts(), $this->children));
    }

    /**
     * Is this a compound condition about data — a `&&` chain that reaches into members two or more times, and
     * is not only a run of type checks?
     */
    public function isSubstantiveGuard(): bool
    {
        $conjuncts = $this->conjuncts();
        $reaches = array_sum(array_map(static fn (self $conjunct): int => count(array_filter($conjunct->flatten(), static fn (self $node): bool => $node->is('SimpleMemberAccessExpression'))), $conjuncts));

        return $this->is('LogicalAndExpression')
            && $reaches >= 2
            && ! array_all($conjuncts, static fn (self $conjunct): bool => $conjunct->is('IsPatternExpression', 'IsExpression'));
    }

    /**
     * Is this a check of a value's type — `x is Box`, `x is Box b`, `x is Box { Lid: not null }`? A bare
     * `x is { } bound` names no type: it only checks for `null`.
     */
    public function isTypeCheck(): bool
    {
        $pattern = $this->is('IsPatternExpression') ? $this->children[1] : null;

        return $this->is('IsExpression')
            || $pattern?->is('DeclarationPattern', 'TypePattern') === true
            || ($pattern?->is('RecursivePattern') === true && array_any($pattern->children, static fn (self $part): bool => $part->role === 'type'));
    }

    /**
     * Is this a `&&` chain that narrows a value through two or more type checks —
     * `node is Invocation call && call.Target is MemberAccess`?
     */
    public function isTypeNarrowingGuard(): bool
    {
        return $this->is('LogicalAndExpression') && count(array_filter($this->conjuncts(), static fn (self $conjunct): bool => $conjunct->isTypeCheck())) >= 2;
    }

    /**
     * What this compound condition asks, whatever order its conditions are written in.
     */
    public function guardFingerprint(): string
    {
        $hashes = array_map(static fn (self $conjunct): string => StructuralHash::ofExpression($conjunct), $this->conjuncts());
        sort($hashes);

        return sha1(implode('|', $hashes));
    }

    /**
     * What this `with` copy changes, when every change is a constant — `Status=Status.Shipped` for
     * `order with { Status = Status.Shipped }` — sorted, so the order they are written in does not matter;
     * none for a change worked out at the site, for a copy of `this` (the record naming the operation
     * itself), or for anything but a `with`.
     *
     * @return list<string>
     */
    public function constantChanges(): array
    {
        $initializer = $this->is('WithExpression') && ! $this->children[0]->is('ThisExpression') ? ($this->children[1] ?? null) : null;
        $changes = array_filter($initializer?->children ?? [], static fn (self $entry): bool => $entry->is('SimpleAssignmentExpression'));

        if ($changes === [] || ! array_all($changes, static fn (self $change): bool => $change->children[1]->isConstant())) {
            return [];
        }

        $slots = array_map(static fn (self $change): string => $change->children[0]->name . '=' . StructuralHash::ofExpression($change->children[1]), $changes);
        sort($slots);

        return $slots;
    }

    /**
     * The types this `switch` asks its subject to be, one per case that tests a type — `Circle` and `Square` in
     * `shape switch { Circle c => …, Square s => … }` — as the compiler resolved them.
     *
     * @return list<string>
     */
    public function switchedTypes(): array
    {
        $patterns = match (true) {
            $this->is('SwitchExpression') => array_map(static fn (self $arm): self => $arm->children[0], array_slice($this->children, 1)),
            $this->is('SwitchStatement') => array_map(
                static fn (self $label): self => $label->children[0],
                array_filter(array_merge([], ...array_map(static fn (self $section): array => $section->children, array_slice($this->children, 1))), static fn (self $child): bool => $child->is('CasePatternSwitchLabel', 'CaseSwitchLabel')),
            ),
            default => [],
        };
        $tested = array_filter($patterns, static fn (self $pattern): bool => $pattern->is('DeclarationPattern', 'TypePattern', 'RecursivePattern'));
        $types = array_merge([], ...array_map(static fn (self $pattern): array => array_filter($pattern->children, static fn (self $part): bool => $part->role === 'type' && $part->type !== null), $tested));
        $named = array_filter($patterns, static fn (self $label): bool => $label->isTypeName());

        return array_values(array_map(static fn (self $type): string => (string) $type->type?->name, [...$types, ...$named]));
    }

    /**
     * The type of the value this `switch` decides on, as the compiler resolved it, nullability aside.
     */
    public function switchedSubjectType(): string
    {
        return rtrim((string) $this->children[0]->type?->name, '?');
    }

    /**
     * Is every type this switch tests declared inside the type it switches on — `ListingResult.Corrected`,
     * `ListingResult.Skipped` — a closed union written as one type, which is meant to be consumed by switching
     * over its cases, as an enum is?
     */
    public function isSwitchOverOwnCases(): bool
    {
        $subject = $this->switchedSubjectType();

        return $this->switchedTypes() !== [] && array_all($this->switchedTypes(), static fn (string $case): bool => str_starts_with($case, $subject . '.'));
    }

    /**
     * Does every arm of this switch expression build a new object — a mapper turning each type into another,
     * which belongs with the code that owns the type it builds, not on the types it reads?
     */
    public function isTranslatingEveryArm(): bool
    {
        $answers = array_map(static fn (self $arm): self => array_last($arm->children)->withoutParentheses(), array_filter(array_slice($this->children, 1), static fn (self $arm): bool => ! $arm->children[0]->is('DiscardPattern')));

        return $this->is('SwitchExpression') && $answers !== [] && array_all($answers, static fn (self $answer): bool => $answer->is('ObjectCreationExpression', 'ImplicitObjectCreationExpression'));
    }

    /**
     * Is this a `case` label's value that names a type — `Circle` in `case Circle:`? A label's value must be a
     * constant, so a name there that is none can only be a type.
     */
    private function isTypeName(): bool
    {
        return $this->is('IdentifierName', 'QualifiedName') && ! $this->constant && $this->type !== null;
    }

    /**
     * The own member this reference names — `cents` for a bare `cents` or for `this.cents` — empty for anything
     * else, `other.cents` included.
     */
    public function memberName(): string
    {
        return match (true) {
            $this->is('IdentifierName') => (string) $this->name,
            $this->is('SimpleMemberAccessExpression') && $this->children[0]->is('ThisExpression') => (string) $this->children[1]->name,
            default => '',
        };
    }

    /**
     * Is this bare name one $member declares a local or parameter of its own under, so it does not name the
     * type's field? `this.x` always names the field.
     */
    public function isShadowedIn(self $member): bool
    {
        return $this->is('IdentifierName') && in_array($this->name, $member->ownNames(), true);
    }

    /**
     * The places in this expression that name one of $own — bare, or through `this.` — never the member side of
     * `other.x`, nor the member an object initializer sets on the object it builds.
     *
     * @param  list<string>  $own
     * @return list<self>
     */
    public function ownStateReferences(array $own): array
    {
        if ($this->is('SimpleMemberAccessExpression')) {
            return $this->children[0]->is('ThisExpression') && in_array($this->children[1]->name, $own, true) ? [$this] : $this->children[0]->ownStateReferences($own);
        }

        if ($this->is('IdentifierName')) {
            return $this->role === 'expression' && in_array($this->name, $own, true) ? [$this] : [];
        }

        $initializing = str_ends_with($this->kind, 'InitializerExpression');
        $parts = array_map(static fn (self $child): self => $initializing && $child->is('SimpleAssignmentExpression') ? $child->children[1] : $child, $this->children);

        return array_merge([], ...array_map(static fn (self $part): array => $part->ownStateReferences($own), $parts));
    }

    /**
     * Is this field or property declared with a type that admits `null` — `Batch?`, `int?`?
     */
    public function declaresNullableState(): bool
    {
        $type = $this->is('FieldDeclaration') ? ($this->children[0]->children[0] ?? null) : (array_values(array_filter($this->children, static fn (self $child): bool => $child->role === 'type'))[0] ?? null);

        return $type?->is('NullableType') === true;
    }

    /**
     * The names this field or property holds state under.
     *
     * @return list<string>
     */
    public function heldStateNames(): array
    {
        $declarators = $this->is('FieldDeclaration') ? array_filter($this->descendants(), static fn (self $node): bool => $node->is('VariableDeclarator')) : [$this];

        return array_values(array_filter(array_map(static fn (self $declarator): ?string => $declarator->name, $declarators)));
    }

    /**
     * The value this field or property declaration starts $name with, where it gives one.
     *
     * @return list<self>
     */
    public function initialValuesOf(string $name): array
    {
        $holders = match (true) {
            $this->is('FieldDeclaration') => array_filter($this->descendants(), static fn (self $node): bool => $node->is('VariableDeclarator')),
            $this->is('PropertyDeclaration') => [$this],
            default => [],
        };
        $named = array_filter($holders, static fn (self $holder): bool => $holder->name === $name);
        $clauses = array_merge([], ...array_map(static fn (self $holder): array => array_filter($holder->children, static fn (self $child): bool => $child->is('EqualsValueClause')), $named));

        return array_values(array_map(static fn (self $clause): self => $clause->children[0], $clauses));
    }

    /**
     * Is this the declaration of a type — a class, record, struct, interface or enum?
     */
    public function isTypeDeclaration(): bool
    {
        return $this->role === 'member' && $this->is('ClassDeclaration', 'RecordDeclaration', 'RecordStructDeclaration', 'StructDeclaration', 'InterfaceDeclaration', 'EnumDeclaration');
    }

    /**
     * The words this member's signature already says — its name, its parameters' and type parameters' names,
     * and the names of the types it takes and returns — what a doc comment repeating it would say.
     *
     * @return list<string>
     */
    public function signatureWords(): array
    {
        $signature = array_filter($this->children, static fn (self $child): bool => ! $child->is('Block', 'ArrowExpressionClause', 'AttributeList') && $child->role !== 'member');
        $parts = array_merge($signature, ...array_map(static fn (self $child): array => $child->descendants(), array_values($signature)));

        return Prose::words(implode(' ', array_filter([$this->name, ...array_map(static fn (self $part): ?string => $part->name, $parts)])));
    }

    /**
     * The name of the namespace this declares, as C# code writes it — `Shop.Orders`, without the compiler's
     * `global::` qualifier.
     */
    public function namespaceName(): string
    {
        $symbol = (string) $this->symbol;

        return str_starts_with($symbol, 'global::') ? substr($symbol, strlen('global::')) : $symbol;
    }

    /**
     * The conversion this call hands each scalar parameter of the method it calls, by position — a cast to a
     * scalar type, `X.Parse(…)`, `Convert.ToX(…)` or `.ToString()` written as the whole argument. None for a
     * call that names its arguments, whose positions are not the parameters'.
     *
     * @return array<int, string>
     */
    public function scalarConversions(): array
    {
        if ($this->target === null || ! $this->passesByPosition()) {
            return [];
        }

        $scalar = array_filter($this->arguments(), fn (self $argument, int $position) => $this->fillsScalarAt($position), ARRAY_FILTER_USE_BOTH);

        return array_filter(array_map(static fn (self $argument): ?string => $argument->conversion(), $scalar), static fn (?string $conversion): bool => $conversion !== null);
    }

    /**
     * Does this call pass every argument by position — no `name:` — so argument $n fills parameter $n?
     */
    public function passesByPosition(): bool
    {
        $list = array_values(array_filter($this->children, static fn (self $child): bool => $child->is('ArgumentList')))[0] ?? null;

        return $list !== null && ! array_any($list->children, static fn (self $argument): bool => array_any($argument->children, static fn (self $part): bool => $part->is('NameColon')));
    }

    /**
     * Is the parameter at $position of the method this call reaches a scalar — text, a number, a date, a flag?
     */
    public function fillsScalarAt(int $position): bool
    {
        return in_array($this->target?->parameters[$position] ?? null, self::SCALARS, true);
    }

    /**
     * The name a member chain hangs off — `request` in `request.ChannelId` or `order.Customer.Name` — null for
     * anything that is not a chain of member reads rooted at a name.
     */
    public function projectionRoot(): ?self
    {
        return match (true) {
            ! $this->is('SimpleMemberAccessExpression') => null,
            $this->children[0]->is('IdentifierName') => $this->children[0],
            default => $this->children[0]->projectionRoot(),
        };
    }

    /**
     * The name this call is made on — `store` for `store.Persist(…)` — null for a call made on nothing named.
     */
    public function receiverName(): ?string
    {
        $callee = $this->children[0] ?? null;

        if ($callee === null || ! $callee->is('SimpleMemberAccessExpression')) {
            return null;
        }

        return $callee->children[0]->is('IdentifierName') ? $callee->children[0]->name : $callee->children[0]->projectionRoot()?->name;
    }

    /**
     * The member path a projection reads off its root — `.Customer.Name` for `order.Customer.Name` — empty for
     * anything that is not a projection.
     */
    public function projectionPath(): string
    {
        return match (true) {
            ! $this->is('SimpleMemberAccessExpression') => '',
            $this->children[0]->is('IdentifierName') => ".{$this->children[1]->name}",
            default => $this->children[0]->projectionPath() . ".{$this->children[1]->name}",
        };
    }

    /**
     * The name of the member this call calls — `Persist` for `store.Persist(…)` and `Persist(…)` alike.
     */
    public function calledName(): ?string
    {
        return ($this->children[0] ?? null)?->referencedName();
    }

    /**
     * The name this reference ends in — `Persist` for `Persist` and for `store.Persist` alike; null for anything
     * that is not a name or a member read.
     */
    public function referencedName(): ?string
    {
        return match (true) {
            $this->is('IdentifierName') => $this->name,
            $this->is('SimpleMemberAccessExpression') => $this->children[1]->name,
            default => null,
        };
    }

    /**
     * The conversion this expression is, when it is one — a cast to a scalar type, `X.Parse(…)` on a scalar
     * type, `Convert.ToX(…)`, or a value's `.ToString()` — null when it is none, or when what it converts is a
     * constant: a literal written in another type is spelled, not held.
     */
    private function conversion(): ?string
    {
        $expression = $this->withoutParentheses();
        $called = $expression->isCall() ? $expression->target : null;
        $converted = $expression->converted();

        if ($converted === null || $converted->isConstant()) {
            return null;
        }

        return match (true) {
            $expression->is('CastExpression') && in_array($expression->type?->name, self::SCALARS, true) => "({$expression->type->name})",
            $called?->name === 'ToString' && $converted->type?->isValueType === true => 'ToString()',
            $called?->name === 'Parse' && in_array($called->type, self::SCALARS, true) => "{$called->type}.Parse",
            $called?->type === 'global::System.Convert' => "Convert.{$called->name}",
            default => null,
        };
    }

    /**
     * What this cast or call converts — the operand of a cast, the receiver of a `.ToString()` taking no
     * arguments, or the one argument of any other call; null for anything else.
     */
    private function converted(): ?self
    {
        $arguments = $this->arguments();

        return match (true) {
            $this->is('CastExpression') => $this->expressions()[0] ?? null,
            ! $this->isCall() => null,
            $this->target?->name === 'ToString' && $arguments === [] && $this->children[0]->is('SimpleMemberAccessExpression') => $this->children[0]->children[0],
            count($arguments) >= 1 => $arguments[0],
            default => null,
        };
    }

    /**
     * The parameters this member, local function or lambda declares, in order.
     *
     * @return list<self>
     */
    public function parameters(): array
    {
        return array_merge([], ...array_map(static fn (self $list): array => $list->children, array_values(array_filter($this->children, static fn (self $child): bool => $child->is('ParameterList')))));
    }

    /**
     * The name a chain of reads and calls hangs off — `workflow` in `workflow.Graph.Node(id)` or
     * `workflow.Graph.Nodes[id]` — null for a chain that starts anywhere but a name.
     */
    public function chainRoot(): ?self
    {
        return match (true) {
            $this->is('IdentifierName') => $this,
            $this->is('SimpleMemberAccessExpression', 'InvocationExpression', 'ElementAccessExpression', 'SuppressNullableWarningExpression') => $this->children[0]->chainRoot(),
            default => null,
        };
    }

    /**
     * Is this an `if` with no `else` whose every statement throws — a guard that refuses and nothing more?
     */
    public function isGuardThatOnlyThrows(): bool
    {
        $statements = array_values(array_filter($this->children(), static fn (self $child): bool => ! $child->is('ElseClause')));
        $branch = $statements[0] ?? null;
        $thrown = $branch?->is('Block') === true ? $branch->children() : array_filter([$branch]);

        return $this->is('IfStatement')
            && ! array_any($this->children, static fn (self $child): bool => $child->is('ElseClause'))
            && $thrown !== []
            && array_all($thrown, static fn (self $statement): bool => $statement->is('ThrowStatement'));
    }

    /**
     * The name this lookup is made on, when it is keyed solely by one of $keys — `workflow` for
     * `workflow.Graph.Node(nodeId)` and `workflow.Graph.Nodes[nodeId]` — null for anything that is not such a
     * lookup.
     *
     * @param  list<string>  $keys
     */
    public function lookupRootKeyedBy(array $keys): ?self
    {
        $arguments = $this->arguments();
        $keyed = count($arguments) === 1 && $arguments[0]->is('IdentifierName') && in_array($arguments[0]->name, $keys, true);

        return match (true) {
            ! $keyed => null,
            $this->is('ElementAccessExpression') => $this->children[0]->chainRoot(),
            $this->is('InvocationExpression') && $this->children[0]->is('SimpleMemberAccessExpression') => $this->children[0]->children[0]->chainRoot(),
            default => null,
        };
    }

    /**
     * Is this body the resolver of $local — the lookup kept in it, guards that only throw, and $local returned?
     * That is the one place a resolution and its refusal belong.
     */
    public function isResolverReturning(string $local): bool
    {
        $statements = $this->children();
        $last = end($statements);

        if (count($statements) < 2 || $last === false || ! $last->is('ReturnStatement') || ($last->expressions()[0] ?? null)?->name !== $local) {
            return false;
        }

        return array_all(array_slice($statements, 1, -1), static fn (self $guard): bool => $guard->isGuardThatOnlyThrows());
    }

    /**
     * Does this statement leave where it stands — `return`, `throw`, `continue`, `break`, `yield break`?
     */
    public function isBailOut(): bool
    {
        return $this->is('ReturnStatement', 'ThrowStatement', 'ContinueStatement', 'BreakStatement', 'YieldBreakStatement');
    }

    /**
     * Does this node run a body of its own — a member, an accessor, a local function, a lambda?
     */
    public function isFunction(): bool
    {
        return $this->is(...self::FUNCTIONS);
    }

    public function hasModifier(string $modifier): bool
    {
        return in_array($modifier, $this->modifiers, true);
    }

    /**
     * The child nodes that are not expressions — statements, members, parameters, clauses.
     */
    public function children(): array
    {
        return array_values(array_filter($this->children, static fn (self $child): bool => ! $child->isExpression()));
    }

    /**
     * The expressions this node holds directly.
     *
     * @return list<self>
     */
    public function expressions(): array
    {
        return array_values(array_filter($this->children, static fn (self $child): bool => $child->isExpression()));
    }

    /**
     * Every node beneath this one that is not an expression, at any depth — reached through expressions
     * too, so the statements of a lambda's block are found.
     *
     * @return list<self>
     */
    public function descendants(): array
    {
        $all = [];

        foreach ($this->children as $child) {
            if (! $child->isExpression()) {
                $all[] = $child;
            }

            $all = [...$all, ...$child->descendants()];
        }

        return $all;
    }

    /**
     * What tells this node from another of the same shape — its kind, which in C# already names its
     * operator (`AddExpression`, `SubtractAssignmentExpression`).
     */
    public function variant(): string
    {
        return $this->kind;
    }

    public function declaredNames(): array
    {
        return $this->name !== null && ! $this->isExpression() ? [$this->name] : [];
    }

    /**
     * The body a method, constructor, accessor, local function or lambda runs — its block, or the
     * expression after `=>`.
     *
     * @return Option<SyntaxNode>
     */
    public function functionBody(): Option
    {
        if (! $this->is(...self::FUNCTIONS)) {
            return Option::none();
        }

        foreach ($this->children as $child) {
            if ($child->is('Block', 'ArrowExpressionClause')) {
                return Option::some($child);
            }
        }

        return Option::none();
    }

    /**
     * A `return`, or an expression body (`=> value`) that hands its value back.
     */
    public function isReturn(): bool
    {
        return $this->kind === 'ReturnStatement' || ($this->is('ArrowExpressionClause') && ! $this->isVoid());
    }

    /**
     * @return Option<self>
     */
    public function returnedValue(): Option
    {
        return $this->isReturn() ? Option::fromNullable($this->expressions()[0] ?? null) : Option::none();
    }

    /**
     * An expression run for its effect — a statement, or the expression body of a `void` member.
     */
    public function isExpressionStatement(): bool
    {
        return $this->kind === 'ExpressionStatement' || ($this->is('ArrowExpressionClause') && $this->isVoid());
    }

    /**
     * Is this an expression body whose call returns nothing?
     */
    private function isVoid(): bool
    {
        return $this->expressions()[0]->type?->name === 'global::System.Void';
    }

    public function kindName(): string
    {
        return $this->kind;
    }

    public function isCall(): bool
    {
        return $this->kind === 'InvocationExpression';
    }

    /**
     * Does this expression have a value the compiler fixes — a literal, an enum member, a `const`, or
     * arithmetic on them? What a lookup table answers with and a ladder compares against.
     */
    public function isConstant(): bool
    {
        return $this->constant;
    }

    /**
     * What this condition compares with a constant — `status` in `status == Status.Paid` or in
     * `status is Status.Paid` — and none for a condition that is not such a comparison.
     *
     * @return Option<self>
     */
    public function comparisonSubject(): Option
    {
        if ($this->is('IsPatternExpression') && ($this->children[1] ?? null)?->is('ConstantPattern') === true) {
            return Option::some($this->children[0]);
        }

        if ($this->is('IsExpression') && ($this->children[1] ?? null)?->isConstant() === true) {
            return Option::some($this->children[0]);
        }

        if (! $this->is('EqualsExpression')) {
            return Option::none();
        }

        [$left, $right] = $this->expressions();

        if ($left->isConstant() === $right->isConstant()) {
            return Option::none();
        }

        return Option::some($left->isConstant() ? $right : $left);
    }

    /**
     * Is this a literal written in the source — a string, a number, a character, `true`, `null`, `default`?
     */
    public function isLiteral(): bool
    {
        return str_ends_with($this->kind, 'LiteralExpression');
    }

    /**
     * A switch expression answers with each arm's value, a conditional with either branch — each read
     * the same way, since an arm can itself pick.
     *
     * @return list<self>
     */
    public function answers(): array
    {
        return match ($this->kind) {
            'SwitchExpression' => array_merge(...array_map(static fn (self $arm): array => $arm->expressions()[array_key_last($arm->expressions())]->answers(), array_values(array_filter($this->children, static fn (self $child): bool => $child->is('SwitchExpressionArm'))))),
            'ConditionalExpression' => [...$this->expressions()[1]->answers(), ...$this->expressions()[2]->answers()],
            default => [$this],
        };
    }

    /**
     * This expression and every expression beneath it, reached through any node between them.
     *
     * @return list<self>
     */
    public function flatten(): array
    {
        $all = [$this];

        foreach ($this->children as $child) {
            $all = [...$all, ...($child->isExpression() ? $child->flatten() : array_merge([], ...array_map(static fn (self $inner): array => $inner->flatten(), $child->outermostExpressions())))];
        }

        return $all;
    }

    /**
     * The children by the part each plays: a call's callee before the rest, a member access's receiver
     * and the name of the member it reads; any other node's children in order.
     *
     * @return array<string, mixed>
     */
    private function parts(): array
    {
        return match (true) {
            $this->isCall() => ['callee' => $this->children[0], 'children' => array_slice($this->children, 1)],
            $this->is('SimpleMemberAccessExpression', 'PointerMemberAccessExpression') => ['receiver' => $this->children[0], 'member' => $this->children[1]->name],
            $this->is('MemberBindingExpression') => ['member' => $this->children[0]->name],
            default => ['children' => $this->children],
        };
    }

    /**
     * The outermost expressions beneath this node, however deep the nodes between.
     *
     * @return list<self>
     */
    public function outermostExpressions(): array
    {
        $found = [];

        foreach ($this->children as $child) {
            $found = [...$found, ...($child->isExpression() ? [$child] : $child->outermostExpressions())];
        }

        return $found;
    }

}
