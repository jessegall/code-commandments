<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py;

use Closure;
use JesseGall\CodeCommandments\ModuleCodebase;
use JesseGall\CodeCommandments\ExcludedPaths;
use JesseGall\CodeCommandments\Files\FileQuery;
use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\Expr\ExprKind;
use JesseGall\CodeCommandments\Py\Node\Block;
use JesseGall\CodeCommandments\Py\Node\ClassDef;
use JesseGall\CodeCommandments\Py\Node\ExceptHandler;
use JesseGall\CodeCommandments\Py\Node\FunctionDef;
use JesseGall\CodeCommandments\Py\Node\MatchCase;
use JesseGall\CodeCommandments\Py\Node\Node;
use JesseGall\CodeCommandments\Py\Node\Param;
use JesseGall\CodeCommandments\Support\FileTree;
use JesseGall\CodeCommandments\Support\Path;
use JesseGall\CodeCommandments\WorkingCopy;

/**
 * The Python sources a run judges, and the selectors a Python rule opens its query with — named as the
 * PHP and TypeScript engines name theirs, so a rule about a function, a class or a call reads the same
 * whichever language it judges. Files are parsed on first use.
 */
final class Codebase implements ModuleCodebase
{
    /**
     * @var list<ModuleFile>|null
     */
    private ?array $modules = null;

    private ?CallIndex $index = null;

    private ?Enums $enums = null;

    private ?TypedDicts $typedDicts = null;

    private ?Dataclasses $dataclasses = null;

    /**
     * @var array<string, true>|null  the name of every class a module declares
     */
    private ?array $classNames = null;

    /**
     * @param  array<string, string>  $sources  path => source
     */
    public function __construct(private readonly array $sources) {}

    /**
     * Every `.py` file under $path, read through $overlay's pending edits.
     *
     * @param  string|list<string>  $path
     */
    public static function scan(string|array $path, WorkingCopy $overlay = new WorkingCopy(), ExcludedPaths $excluded = new ExcludedPaths()): self
    {
        $files = [];

        foreach ((array) $path as $root) {
            foreach ([...FileTree::filesIn($root, 'py', $excluded), ...$overlay->createdUnder($root, '.py')] as $file) {
                $files[$file] = true;
            }
        }

        $sources = [];

        foreach (array_keys($files) as $file) {
            $source = $overlay->read($file);

            if ($source !== null) {
                $sources[$file] = $source;
            }
        }

        return new self($sources);
    }

    public static function fromString(string $source, string $path = 'module.py'): self
    {
        return new self([$path => $source]);
    }

    public function focusedOn(string ...$paths): static
    {
        $wanted = Path::setOf($paths);

        return new self(array_filter($this->sources, static fn (string $file): bool => isset($wanted[Path::resolved($file)]), ARRAY_FILTER_USE_KEY));
    }

    public function fileCount(): int
    {
        return count($this->modules());
    }

    /**
     * @return list<ModuleFile>
     */
    public function modules(): array
    {
        return $this->modules ??= array_map(static fn (string $source, string $file) => ModuleFile::fromFile($source, $file), $this->sources, array_keys($this->sources));
    }

    /**
     * The call graph — which calls reach which `def` — built once and kept.
     */
    public function index(): CallIndex
    {
        return $this->index ??= new CallIndex($this);
    }

    /**
     * The enums this codebase declares — found once and kept.
     */
    public function enums(): Enums
    {
        return $this->enums ??= new Enums($this);
    }

    /**
     * The `TypedDict`s this codebase declares — found once and kept.
     */
    public function typedDicts(): TypedDicts
    {
        return $this->typedDicts ??= new TypedDicts($this);
    }

    /**
     * Does a module here declare a class named as $dotted ends — `Circle` for `shapes.Circle`? Judging a
     * subtree can only answer no for a class declared outside it, never yes for one that is not declared.
     */
    public function declaresClass(string $dotted): bool
    {
        $this->classNames ??= array_fill_keys(array_merge([], ...array_map(
            static fn (ModuleFile $module): array => array_map(static fn (ClassDef $class): string => $class->name, array_values(array_filter($module->nodes(), static fn (Node $node): bool => $node instanceof ClassDef))),
            $this->modules(),
        )), true);
        $parts = explode('.', $dotted);

        return isset($this->classNames[end($parts)]);
    }

    /**
     * The dataclasses this codebase declares — found once and kept.
     */
    public function dataclasses(): Dataclasses
    {
        return $this->dataclasses ??= new Dataclasses($this);
    }

    public function whereFile(): FileQuery
    {
        return new FileQuery(array_keys($this->sources));
    }

    /**
     * Every `def` — a module function, a method and a nested function alike.
     */
    public function whereFunction(): Query
    {
        return $this->whereNode(static fn (Node $node): bool => $node instanceof FunctionDef);
    }

    /**
     * Every `def` written directly in a class body.
     */
    public function whereMethodDeclaration(): Query
    {
        return $this->whereFunction()->where(static fn (NodeMatch $match): bool => $match->isMethod());
    }

    public function whereClass(): Query
    {
        return $this->whereNode(static fn (Node $node): bool => $node instanceof ClassDef);
    }

    /**
     * Every statement — not the blocks, parameters, handlers and cases the tree gives nodes of their own.
     */
    public function whereStatement(): Query
    {
        return $this->whereNode(static fn (Node $node): bool => ! ($node instanceof Block || $node instanceof Param || $node instanceof ExceptHandler || $node instanceof MatchCase));
    }

    /**
     * Every node $select keeps.
     *
     * @param  Closure(Node): bool  $select
     */
    public function whereNode(Closure $select): Query
    {
        return new Query($this->nodePairs(...), $select);
    }

    public function whereCall(): ExprQuery
    {
        return $this->whereExpression(static fn (Expr $expr): bool => $expr->is(ExprKind::Call));
    }

    /**
     * Every expression $select keeps, at any depth.
     *
     * @param  Closure(Expr): bool  $select
     */
    public function whereExpression(Closure $select): ExprQuery
    {
        return new ExprQuery($this->expressionPairs(...), $select);
    }

    /**
     * @return list<array{0: Node, 1: ModuleFile}>
     */
    private function nodePairs(): array
    {
        $pairs = [];

        foreach ($this->modules() as $module) {
            foreach ($module->nodes() as $node) {
                $pairs[] = [$node, $module];
            }
        }

        return $pairs;
    }

    /**
     * @return list<array{0: Expr, 1: ModuleFile}>
     */
    private function expressionPairs(): array
    {
        $pairs = [];

        foreach ($this->modules() as $module) {
            foreach ($module->expressions() as $expression) {
                $pairs[] = [$expression, $module];
            }
        }

        return $pairs;
    }
}
