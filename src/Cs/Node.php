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
    ) {}

    /**
     * @param  array<string, mixed>  $written  a node as the bridge's contract writes it
     */
    public static function fromBridge(array $written): self
    {
        $target = $written['target'] ?? null;

        return new self(
            kind: (string) $written['kind'],
            role: (string) $written['role'],
            children: array_map(self::fromBridge(...), $written['children'] ?? []),
            name: $written['name'] ?? null,
            text: $written['text'] ?? null,
            operator: $written['operator'] ?? null,
            modifiers: $written['modifiers'] ?? [],
            type: isset($written['type']) ? new ResolvedType($written['type'], $written['nullable']) : null,
            target: is_array($target) ? new CallTarget($target['type'], $target['name'], $target['parameters'] ?? []) : null,
            symbol: $written['symbol'] ?? null,
            inherited: array_key_exists('inherited', $written),
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

    public function isReturn(): bool
    {
        return $this->kind === 'ReturnStatement';
    }

    /**
     * @return Option<self>
     */
    public function returnedValue(): Option
    {
        return $this->isReturn() ? Option::fromNullable($this->expressions()[0] ?? null) : Option::none();
    }

    public function isExpressionStatement(): bool
    {
        return $this->kind === 'ExpressionStatement';
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
     * Is this a literal — a string, a number, `true`, `null`, `default` — with nothing computed in it?
     */
    public function isConstant(): bool
    {
        return str_ends_with($this->kind, 'LiteralExpression');
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
            $all = [...$all, ...($child->isExpression() ? $child->flatten() : array_merge([], ...array_map(static fn (self $inner): array => $inner->flatten(), $child->expressionsWithin())))];
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
    private function expressionsWithin(): array
    {
        $found = [];

        foreach ($this->children as $child) {
            $found = [...$found, ...($child->isExpression() ? [$child] : $child->expressionsWithin())];
        }

        return $found;
    }

}
