<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cs;

use JesseGall\CodeCommandments\Positioned;
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
            type: isset($written['type']) ? $vocabulary->type($written['type'], $written['nullable']) : null,
            target: is_array($target) ? $vocabulary->target($target['type'], $target['name'], $target['parameters'] ?? []) : null,
            symbol: $vocabulary->maybe($written['symbol'] ?? null),
            inherited: array_key_exists('inherited', $written),
            constant: array_key_exists('constant', $written),
            forgivesNull: array_key_exists('forgivesNull', $written),
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
