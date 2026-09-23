<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py;

use JesseGall\CodeCommandments\Located;
use JesseGall\CodeCommandments\Py\Node\Block;
use JesseGall\CodeCommandments\Py\Node\ClassDef;
use JesseGall\CodeCommandments\Py\Node\FunctionDef;
use JesseGall\CodeCommandments\Py\Node\IfStmt;
use JesseGall\CodeCommandments\Py\Node\Node;
use JesseGall\CodeCommandments\ReadsFunctionBody;
use JesseGall\CodeCommandments\Span;
use JesseGall\CodeCommandments\Support\ClassName;

/**
 * A Python node a query selected, together with the module it sits in — so it knows its `file:line`.
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
     * The name this node declares — a function's, a class's, a parameter's. Empty for a statement.
     */
    public function name(): string
    {
        return $this->node->declaredNames()[0] ?? '';
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
     * What the node IS, named where it has a name — `FunctionDef load`, `ClassDef Store`.
     */
    public function scope(): string
    {
        $kind = ClassName::short($this->node::class);

        return $this->name() === '' ? $kind : "{$kind} {$this->name()}";
    }

    /**
     * Is this a `def` written directly in a class body?
     */
    public function isMethod(): bool
    {
        return $this->node instanceof FunctionDef && $this->module->isMethod($this->node);
    }

    /**
     * How many choices this node sits inside within its own function or class body — each block an `if`,
     * a loop or a `match` owns is one level, so an `elif` adds none and a `try` or a `with` never counts.
     */
    public function branchingDepth(): int
    {
        $depth = 0;
        $child = $this->node;

        foreach ($this->enclosing() as $parent) {
            if ($child instanceof Block && $parent->isBranchingConstruct()) {
                $depth++;
            }

            $child = $parent;
        }

        return $depth;
    }

    /**
     * Is this an `elif` — an `if` standing as another `if`'s else, one rung of its chain?
     */
    public function isElif(): bool
    {
        return $this->node instanceof IfStmt && $this->module->parentOf($this->node)->isSomeAnd(static fn (Node $parent): bool => $parent instanceof IfStmt);
    }

    /**
     * Is this a class's `__init__` — structure every class declares for itself, which two classes cannot
     * share however alike they read?
     */
    public function isConstructorDeclaration(): bool
    {
        return $this->node instanceof FunctionDef && $this->node->name === '__init__' && $this->isMethod();
    }

    /**
     * Is the function body only placeholders — an abstract or protocol method, an overload, a hook left
     * for subclasses? Every stub reads alike, and none holds logic to share.
     */
    public function isStub(): bool
    {
        return $this->node->functionBody()->isSomeAnd(
            static fn (Block $body): bool => array_all($body->children(), static fn (Node $statement): bool => $statement->isPlaceholder()),
        );
    }

    protected static function syntaxHash(): string
    {
        return StructuralHash::class;
    }

    /**
     * The nodes around this one, innermost first, up to the `def` or `class` it belongs to.
     *
     * @return list<Node>
     */
    private function enclosing(): array
    {
        $enclosing = [];

        foreach ($this->module->ancestorsOf($this->node) as $ancestor) {
            if ($ancestor instanceof FunctionDef || $ancestor instanceof ClassDef) {
                break;
            }

            $enclosing[] = $ancestor;
        }

        return $enclosing;
    }
}
