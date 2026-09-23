<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cs;

use JesseGall\CodeCommandments\Located;
use JesseGall\CodeCommandments\ReadsFunctionBody;
use JesseGall\CodeCommandments\Span;
use JesseGall\PhpTypes\Option;

/**
 * A C# node a query found, with the file it is in — a statement, a member or an expression alike.
 * Non-final by design: subclass it to hang domain predicates a `where` closure can type-hint.
 */
class NodeMatch implements Located
{
    use ReadsFunctionBody;

    public function __construct(
        public readonly Node $node,
        public readonly ModuleFile $module,
    ) {}

    /**
     * The name the node declares or reads — a method's, a class's, an identifier's; an accessor answers
     * for the property, indexer or event it belongs to, as the compiler names it (`get_Total`). Empty for
     * anything nameless.
     */
    public function name(): string
    {
        if ($this->node->name !== null || ! str_ends_with($this->node->kind, 'AccessorDeclaration')) {
            return $this->node->name ?? '';
        }

        foreach ($this->module->ancestorsOf($this->node) as $member) {
            if ($member->is('PropertyDeclaration', 'IndexerDeclaration', 'EventDeclaration') && $member->name !== null) {
                return $member->name;
            }
        }

        return '';
    }

    public function line(): int
    {
        return $this->module->lineAt($this->node->start);
    }

    public function file(): string
    {
        return $this->module->file;
    }

    public function location(): string
    {
        return $this->file() . ':' . $this->line();
    }

    public function span(): Span
    {
        return $this->module->spanAt($this->node->start, $this->node->end);
    }

    /**
     * What the node IS, named where it has a name — `MethodDeclaration Total`, `ClassDeclaration Order`.
     */
    public function scope(): string
    {
        return $this->name() === '' ? $this->node->kind : "{$this->node->kind} {$this->name()}";
    }

    public function isConstructorDeclaration(): bool
    {
        return $this->node->is('ConstructorDeclaration');
    }

    /**
     * A body that only says it is not written yet — `throw new NotImplementedException()`, as a block or
     * an expression body. (A member with no body at all — abstract, on an interface, a partial's
     * declaring half — is no function to begin with.)
     */
    public function isStub(): bool
    {
        return $this->node->functionBody()->isSomeAnd(
            static fn (Node $body): bool => self::thrown($body)->isSomeAnd(static fn (Node $exception): bool => $exception->type?->name === 'global::System.NotImplementedException'),
        );
    }

    /**
     * Does this member override a base member or implement an interface's, as the compiler resolved it?
     * Its shape is the contract's before it is its own.
     */
    public function isOverride(): bool
    {
        return $this->node->inherited;
    }

    /**
     * What $body throws when throwing is all it does — `{ throw …; }` or `=> throw …`.
     *
     * @return Option<Node>
     */
    private static function thrown(Node $body): Option
    {
        $throw = match (true) {
            $body->is('Block') && count($body->children()) === 1 && $body->children()[0]->is('ThrowStatement') => $body->children()[0],
            $body->is('ArrowExpressionClause') && $body->expressions()[0]->is('ThrowExpression') => $body->expressions()[0],
            default => null,
        };

        return Option::fromNullable($throw?->expressions()[0] ?? null);
    }

    protected static function syntaxHash(): string
    {
        return StructuralHash::class;
    }
}
