<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py;

use JesseGall\CodeCommandments\Language;
use JesseGall\CodeCommandments\NodeSpans;
use JesseGall\CodeCommandments\ParsedModule;
use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\Expr\ExprKind;
use JesseGall\CodeCommandments\Py\Node\ClassDef;
use JesseGall\CodeCommandments\Py\Node\FunctionDef;
use JesseGall\CodeCommandments\Py\Node\IfStmt;
use JesseGall\CodeCommandments\Py\Node\Module;
use JesseGall\CodeCommandments\Py\Node\Node;
use JesseGall\CodeCommandments\Py\Node\WhileLoop;
use JesseGall\CodeCommandments\Span;
use JesseGall\PhpTypes\Option;

/**
 * One parsed Python file: its module tree, its path and source — so a node can say which line it is on
 * — and the flat views a selector filters: every node, every expression, which functions are methods.
 */
final class ModuleFile implements ParsedModule
{
    use NodeSpans;

    /**
     * @var list<Node>|null
     */
    private ?array $nodes = null;

    /**
     * @var list<Expr>|null
     */
    private ?array $expressions = null;

    /**
     * @var array<int, true>|null  the object ids of every `def` written directly in a class body
     */
    private ?array $methods = null;

    /**
     * @var array<int, Node>|null  each node's parent, by the child's object id
     */
    private ?array $parents = null;

    /**
     * @var array<int, Expr>|null  the expression each sub-expression sits in, by its object id
     */
    private ?array $wrappers = null;

    /**
     * @var array<int, Node>|null  the node holding each expression, by the expression's object id
     */
    private ?array $owners = null;

    private function __construct(
        public readonly Module $module,
        public readonly string $file,
        public readonly string $source,
    ) {}

    public static function fromFile(string $source, string $file): self
    {
        return new self(Parser::module($source), $file, $source);
    }

    /**
     * Every node in the module, parents before their children, walked once.
     *
     * @return list<Node>
     */
    public function nodes(): array
    {
        return $this->nodes ??= $this->module->descendants();
    }

    /**
     * Every expression the module holds, each sub-expression included.
     *
     * @return list<Expr>
     */
    public function expressions(): array
    {
        return $this->expressions ??= array_merge(...array_map(
            static fn (Node $node): array => array_merge([], ...array_map(static fn (Expr $expression): array => $expression->flatten(), $node->expressions())),
            $this->nodes(),
        ));
    }

    /**
     * Does anything written inside $scope ask whether $dotted is blank — compare it to `''`, negate it,
     * or test it bare as the condition of an `if`, a `while` or a conditional expression?
     */
    public function asksBlanknessOf(Node $scope, string $dotted): bool
    {
        return array_any($this->expressions(), fn (Expr $expression): bool => $this->isWithin($expression, $scope)
            && ($expression->testsBlanknessOf($dotted) || ($expression->dottedName() === $dotted && $this->isTested($expression))));
    }

    /**
     * Is $expression the whole condition of an `if`, a `while` or a conditional expression?
     */
    private function isTested(Expr $expression): bool
    {
        $owner = $this->ownerOf($expression)->isSomeAnd(static fn (Node $node): bool => ($node instanceof IfStmt || $node instanceof WhileLoop) && $node->test === $expression);

        return $owner || $this->wrapperOf($expression)->isSomeAnd(static fn (Expr $around): bool => $around->is(ExprKind::Conditional) && $around->get('test') === $expression);
    }

    /**
     * Is $expression written somewhere inside $scope?
     */
    private function isWithin(Expr $expression, Node $scope): bool
    {
        return $this->ownerOf($expression)->isSomeAnd(fn (Node $owner): bool => $owner === $scope || in_array($scope, $this->ancestorsOf($owner), true));
    }

    /**
     * Is $function written directly in a class body — a method, rather than a module or nested function?
     */
    public function isMethod(FunctionDef $function): bool
    {
        $this->methods ??= $this->methodIds();

        return isset($this->methods[spl_object_id($function)]);
    }

    /**
     * The node $node sits directly inside — none for the module itself.
     *
     * @return Option<Node>
     */
    public function parentOf(Node $node): Option
    {
        $this->parents ??= $this->parentIds($this->module);

        return Option::fromNullable($this->parents[spl_object_id($node)] ?? null);
    }

    /**
     * The expression $expression sits directly inside — none for one a statement holds itself.
     *
     * @return Option<Expr>
     */
    public function wrapperOf(Expr $expression): Option
    {
        $this->wrappers ??= $this->wrapperIds();

        return Option::fromNullable($this->wrappers[spl_object_id($expression)] ?? null);
    }

    /**
     * The expressions $expression sits inside, innermost first, out to the one its statement holds.
     *
     * @return list<Expr>
     */
    public function wrappersOf(Expr $expression): array
    {
        return $this->wrapperOf($expression)->mapOr([], fn (Expr $around) => [$around, ...$this->wrappersOf($around)]);
    }

    /**
     * The node that holds $expression — the statement it is part of.
     *
     * @return Option<Node>
     */
    public function ownerOf(Expr $expression): Option
    {
        $this->owners ??= $this->ownerIds();

        return Option::fromNullable($this->owners[spl_object_id($expression)] ?? null);
    }

    /**
     * The `def` $expression is written in — none at a module's or a class's top level.
     *
     * @return Option<FunctionDef>
     */
    public function functionOf(Expr $expression): Option
    {
        return $this->ownerOf($expression)->andThen(function (Node $owner): Option {
            $around = array_filter([$owner, ...$this->ancestorsOf($owner)], static fn (Node $node): bool => $node instanceof FunctionDef);

            return Option::fromNullable(array_values($around)[0] ?? null);
        });
    }

    /**
     * The nodes $node sits inside, innermost first, out to the module.
     *
     * @return list<Node>
     */
    public function ancestorsOf(Node $node): array
    {
        $this->parents ??= $this->parentIds($this->module);
        $ancestors = [];

        while (isset($this->parents[spl_object_id($node)])) {
            $node = $this->parents[spl_object_id($node)];
            $ancestors[] = $node;
        }

        return $ancestors;
    }

    /**
     * Does this module's file sit at the path a dotted module name spells — `a.b` at `…/a/b.py` or
     * `…/a/b/__init__.py`?
     */
    public function isNamed(string $dotted): bool
    {
        $path = '/' . str_replace('.', '/', $dotted);

        return str_ends_with($this->file, "{$path}.py") || str_ends_with($this->file, "{$path}/__init__.py");
    }

    /**
     * The function or class this module declares at its top level as $name.
     *
     * @return Option<Node>
     */
    public function declared(string $name): Option
    {
        foreach ($this->module->body as $node) {
            if (($node instanceof FunctionDef || $node instanceof ClassDef) && $node->name === $name) {
                return Option::some($node);
            }
        }

        return Option::none();
    }

    public function language(): Language
    {
        return Language::Python;
    }


    public function lineAt(int $offset): int
    {
        return Span::lineAt($this->source, $offset);
    }

    public function spanAt(int $start, int $end): Span
    {
        return new Span($this->file, $this->source, $start, $end);
    }

    /**
     * @return array<int, true>
     */
    private function methodIds(): array
    {
        $ids = [];

        foreach ($this->nodes() as $node) {
            if (! $node instanceof ClassDef) {
                continue;
            }

            foreach ($node->body->body as $member) {
                if ($member instanceof FunctionDef) {
                    $ids[spl_object_id($member)] = true;
                }
            }
        }

        return $ids;
    }

    /**
     * @return array<int, Node>
     */
    private function parentIds(Node $parent): array
    {
        $parents = [];

        foreach ($parent->children() as $child) {
            $parents[spl_object_id($child)] = $parent;
            $parents += $this->parentIds($child);
        }

        return $parents;
    }

    /**
     * @return array<int, Expr>
     */
    private function wrapperIds(): array
    {
        $wrappers = [];

        foreach ($this->expressions() as $expression) {
            foreach ($expression->subExpressions() as $inner) {
                $wrappers[spl_object_id($inner)] = $expression;
            }
        }

        return $wrappers;
    }

    /**
     * @return array<int, Node>
     */
    private function ownerIds(): array
    {
        $owners = [];

        foreach ($this->nodes() as $node) {
            foreach ($node->expressions() as $expression) {
                foreach ($expression->flatten() as $part) {
                    $owners[spl_object_id($part)] = $node;
                }
            }
        }

        return $owners;
    }
}
