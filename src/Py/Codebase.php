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
use JesseGall\CodeCommandments\Support\HeldTool;
use JesseGall\CodeCommandments\Support\Path;
use JesseGall\CodeCommandments\WorkingCopy;
use JesseGall\PhpTypes\Option;

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
     * @var array<string, ClassDef>|null  every class a module declares, by its name — the first of a name
     */
    private ?array $classNames = null;

    private ?Types $types = null;

    private ?AttributeFlow $attributeFlow = null;

    private ?PackageGraph $packageGraph = null;

    /**
     * @var array<string, list<ModuleFile>>|null  each module under the full dotted name Python imports it by
     */
    private ?array $byName = null;

    /**
     * @param  array<string, string>  $sources  path => source
     * @param  list<string>  $roots  what was scanned — the whole project mypy types, though only $sources are judged
     * @param  HeldTool<TypeBridge>  $bridge  the mypy bridge, sought only when a rule first asks for a type
     */
    public function __construct(
        private readonly array $sources,
        private readonly array $roots = [],
        private readonly HeldTool $bridge = new HeldTool(TypeBridge::class),
    ) {}

    /**
     * Every `.py` file under $path, read through $overlay's pending edits.
     *
     * @param  string|list<string>  $path
     */
    public static function scan(string|array $path, WorkingCopy $overlay = new WorkingCopy(), ExcludedPaths $excluded = new ExcludedPaths(), HeldTool $types = new HeldTool(TypeBridge::class)): self
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

        return new self($sources, (array) $path, $types);
    }

    public static function fromString(string $source, string $path = 'module.py'): self
    {
        return new self([$path => $source]);
    }

    public function focusedOn(string ...$paths): static
    {
        $wanted = Path::setOf($paths);

        return new self(array_filter($this->sources, static fn (string $file): bool => isset($wanted[Path::resolved($file)]), ARRAY_FILTER_USE_KEY), $this->roots, $this->bridge);
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
     * The types mypy resolves in this codebase, read once and kept — every module under the scanned roots
     * informs them, and only the judged files are written. Empty for a codebase built from strings, and
     * wherever there is no bridge.
     */
    public function types(): Types
    {
        if ($this->roots === []) {
            return $this->types ??= new Types();
        }

        return $this->types ??= $this->bridge->tool()->mapOr(new Types(), fn (TypeBridge $bridge) => $bridge->read($this->roots, array_keys($this->sources)));
    }

    /**
     * Which of this codebase's packages import which — built once and kept.
     */
    public function packageGraph(): PackageGraph
    {
        return $this->packageGraph ??= new PackageGraph($this);
    }

    /**
     * How this codebase reads the attributes of its classes — built once and kept.
     */
    public function attributeFlow(): AttributeFlow
    {
        return $this->attributeFlow ??= new AttributeFlow($this);
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
        return $this->classNamed($dotted)->isSome();
    }

    /**
     * The class a module here declares under the name $dotted ends in — the first, when several share it.
     *
     * @return Option<ClassDef>
     */
    public function classNamed(string $dotted): Option
    {
        if ($this->classNames === null) {
            $this->classNames = [];

            foreach ($this->modules() as $module) {
                foreach ($module->nodes() as $node) {
                    if ($node instanceof ClassDef) {
                        $this->classNames[$node->name] ??= $node;
                    }
                }
            }
        }

        $parts = explode('.', $dotted);

        return Option::fromNullable($this->classNames[end($parts)] ?? null);
    }

    /**
     * The parameters of $method, a method of $host, annotated with an object this codebase owns — a class
     * it declares, neither $host itself nor an enum — the instance's own parameter aside.
     *
     * @return list<string>
     */
    public function ownedParameters(FunctionDef $method, ClassDef $host): array
    {
        $instance = $method->params[0];

        return $method->parameterNamesWhere(function (Param $param) use ($host, $instance): bool {
            $type = $param->annotation?->dottedName() ?? '';
            $short = array_last(explode('.', $type));

            return $param !== $instance && $type !== '' && $short !== $host->name && $this->declaresClass($type) && ! $this->enums()->isEnum($short);
        });
    }

    /**
     * The full dotted name Python imports $module by — its path from the top of its outermost package, each
     * folder on the way a package (an `__init__.py`): `shop.cart` for `shop/cart.py`, `shop` for
     * `shop/__init__.py`, `tool` for a `tool.py` in no package.
     */
    public function fullNameOf(ModuleFile $module): string
    {
        $parts = basename($module->file) === '__init__.py' ? [] : [basename($module->file, '.py')];
        $folder = dirname($module->file);

        while ($this->isPackage($folder)) {
            array_unshift($parts, basename($folder));
            $folder = dirname($folder);
        }

        return implode('.', $parts);
    }

    /**
     * The one module this codebase holds under the full dotted name $dotted — none when it holds none, or
     * more than one.
     *
     * @return Option<ModuleFile>
     */
    public function moduleCalled(string $dotted): Option
    {
        if ($this->byName === null) {
            $this->byName = [];

            foreach ($this->modules() as $module) {
                $this->byName[$this->fullNameOf($module)][] = $module;
            }
        }

        $found = $this->byName[$dotted] ?? [];

        return count($found) === 1 ? Option::some($found[0]) : Option::none();
    }

    /**
     * Does this codebase hold the top-level module or package $name — so that a reference into it can be
     * checked from here?
     */
    public function ownsPackage(string $name): bool
    {
        return $this->moduleCalled($name)->isSome();
    }

    /**
     * Does $dotted name something this codebase holds — a module, or a name bound at the top of the deepest
     * module its prefix names? What follows that name (a method, an attribute) is not checked.
     */
    public function resolves(string $dotted): bool
    {
        $parts = explode('.', $dotted);

        for ($depth = count($parts); $depth >= 1; $depth--) {
            $module = $this->moduleCalled(implode('.', array_slice($parts, 0, $depth)));

            if ($module->isSome()) {
                return $depth === count($parts) || $module->unwrap()->binds($parts[$depth]);
            }
        }

        return false;
    }

    /**
     * Is $folder a package — does this codebase hold its `__init__.py`?
     */
    private function isPackage(string $folder): bool
    {
        return isset($this->sources["{$folder}/__init__.py"]);
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
