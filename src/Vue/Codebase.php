<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

use JesseGall\CodeCommandments\Files\FileQuery;
use JesseGall\CodeCommandments\Vue\Expr\Expr;
use JesseGall\CodeCommandments\Vue\Expr\ExprKind;
use JesseGall\CodeCommandments\Vue\Ts\Node\ClassDecl;
use JesseGall\CodeCommandments\Vue\Ts\Node\FieldDecl;
use JesseGall\CodeCommandments\Vue\Ts\Node\FunctionDecl;
use JesseGall\CodeCommandments\Vue\Ts\Node\IfStmt;
use JesseGall\CodeCommandments\Vue\Ts\Node\MethodDecl;
use JesseGall\CodeCommandments\Vue\Ts\Node\Node as TsNode;
use JesseGall\CodeCommandments\Vue\Ts\Node\Param;
use JesseGall\CodeCommandments\Vue\Ts\Node\Stmt;
use JesseGall\CodeCommandments\Vue\Ts\Node\SwitchStmt;
use JesseGall\CodeCommandments\Vue\Ts\Node\VariableDecl;
use JesseGall\CodeCommandments\ExcludedPaths;
use JesseGall\CodeCommandments\WorkingCopy;
use Closure;
use FilesystemIterator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The frontend twin of the backend {@see \JesseGall\CodeCommandments\Ast\Codebase}:
 * a parsed set of `.vue` components, with the SAME fluent selectors that open a
 * {@see Query}. The element nodes of every template are flattened once and cached,
 * so a selector filters a list rather than re-walking each tree.
 */
final class Codebase implements \JesseGall\CodeCommandments\Codebase
{
    /**
     * @var list<array{0: Element, 1: Sfc}>|null
     */
    private ?array $nodes = null;

    /**
     * @var list<TypeDeclaration>|null
     */
    private ?array $typeDeclarations = null;

    /**
     * @var list<TsModule>|null
     */
    private ?array $modules = null;

    /**
     * @var list<array{0: TsNode, 1: TsModule}>|null
     */
    private ?array $tsNodes = null;

    /**
     * @var list<array{0: Expr, 1: TsModule}>|null
     */
    private ?array $tsExpressions = null;

    /**
     * @param  list<Sfc>  $components
     * @param  list<TypeDeclaration>  $standaloneTypes  types declared in `.ts` files (not in a component)
     * @param  array<string, string>  $typeScript  `.ts` file path => its source, kept so a module rule
     *         reads the same files a type rule already did
     */
    private function __construct(
        private readonly array $components,
        private readonly array $standaloneTypes = [],
        private readonly array $typeScript = [],
    ) {}

    public static function fromString(string $vue, string $path = 'component.vue'): self
    {
        return new self([Sfc::parse($vue, $path)]);
    }

    /**
     * Parse one TypeScript module — the `.ts` twin of {@see fromString}, for a unit test that has a
     * module rather than a component in hand.
     */
    public static function fromTypeScript(string $typeScript, string $path = 'module.ts'): self
    {
        return new self([], [], [$path => $typeScript]);
    }

    /**
     * Parse every `.vue` file under the given root(s).
     *
     * @param  string|list<string>  $path
     * @param  WorkingCopy  $overlay  pending edits to read THROUGH (empty = straight off disk)
     */
    public static function scan(string|array $path, WorkingCopy $overlay = new WorkingCopy(), ExcludedPaths $excluded = new ExcludedPaths()): self
    {
        $vue = [];
        $typeScript = [];

        foreach ((array) $path as $root) {
            foreach (self::filesIn($root, 'vue', $excluded) as $file) {
                $vue[$file] = true;
            }

            foreach (self::filesIn($root, 'ts', $excluded) as $file) {
                $typeScript[$file] = true;
            }

            foreach ($overlay->createdUnder($root, '.vue') as $file) {
                $vue[$file] = true;
            }

            foreach ($overlay->createdUnder($root, '.ts') as $file) {
                $typeScript[$file] = true;
            }
        }

        $components = [];

        foreach (array_keys($vue) as $file) {
            $source = $overlay->read($file);

            if ($source !== null) {
                $components = [...$components, ...self::readable(static fn () => [Sfc::parse($source, $file)], $file)];
            }
        }

        $standaloneTypes = [];
        $sources = [];

        foreach (array_keys($typeScript) as $file) {
            $source = $overlay->read($file);

            if ($source !== null) {
                $sources[$file] = $source;
                $standaloneTypes = [...$standaloneTypes, ...self::readable(
                    static fn () => TypeDeclaration::fromScript(new Script($source), $file, $source),
                    $file,
                )];
            }
        }

        return new self($components, $standaloneTypes, $sources);
    }

    /**
     * What $read yields for one file, or nothing when reading it throws — and a line naming the file
     * so a skip is visible rather than silent. Reading ONE file must never end a scan of thousands:
     * a real tree carries the odd fixture written to be invalid, a half-finished edit, a generated
     * bundle no hand-written grammar was meant to meet.
     *
     * @template T
     * @param  callable(): list<T>  $read
     * @return list<T>
     */
    private static function readable(callable $read, string $file): array
    {
        try {
            return $read();
        } catch (\Throwable $failure) {
            fwrite(STDERR, "⚠ skipped {$file} — it could not be read: {$failure->getMessage()}\n");

            return [];
        }
    }

    /**
     * The parsed components — what a scribe rewrites.
     *
     * @return list<Sfc>
     */
    public function components(): array
    {
        return $this->components;
    }

    /**
     * Every FILE in this codebase — its components and the `.ts` modules beside them — as something
     * a rule can judge by NAME. The SAME query the backend's `whereFile()` opens
     * ({@see \JesseGall\CodeCommandments\Files\FileQuery}): a path is not a language, so the
     * machinery that judges one is not written twice.
     */
    public function whereFile(): FileQuery
    {
        $paths = array_map(static fn (Sfc $component): string => $component->path, $this->components);

        foreach ($this->standaloneTypes as $type) {
            $paths[] = $type->file;
        }

        return new FileQuery(array_values(array_unique($paths)));
    }

    /**
     * Every real element (text and the fragment root excluded).
     */
    public function whereElement(): Query
    {
        return new Query(fn () => $this->nodes(), static fn (Element $element): bool => $element->isElement());
    }

    /**
     * Every static TEXT node — the words a user actually reads, with no `{{ … }}` in them. Copy is
     * where a project's vocabulary shows up outside its identifiers (a caption reading "Taken" over
     * what is an input), and a rule could see only the code around it (#441). Interpolated text is
     * excluded: what it renders is decided elsewhere, so there is nothing here to read.
     */
    public function whereText(): Query
    {
        return new Query(fn () => $this->nodes(), static fn (Element $element): bool => $element->isStaticText());
    }

    /**
     * Elements of one of the given tags.
     */
    public function whereTag(string ...$tags): Query
    {
        return new Query(fn () => $this->nodes(), static fn (Element $element): bool => $element->isElement() && in_array($element->tag, $tags, true));
    }

    /**
     * Open a pattern over every element, checked by your own predicate.
     *
     * @param  Closure(ElementMatch): bool  $check
     */
    public function where(Closure $check): Query
    {
        return $this->whereElement()->where($check);
    }

    /**
     * Every TypeScript object type declared across the codebase — in a component's
     * `<script>` block or a standalone `.ts` file. The declaration-space selector, the
     * sibling of {@see whereElement}: it opens a {@see TypeQuery} the same way.
     */
    public function whereTypeDeclaration(): TypeQuery
    {
        return new TypeQuery(fn () => $this->typeDeclarations(), static fn (TypeDeclaration $declaration) => true);
    }

    /**
     * Every declared type across the codebase — the standalone `.ts` types plus each
     * component's `<script>`-block types — flattened once and cached.
     *
     * @return list<TypeDeclaration>
     */
    public function typeDeclarations(): array
    {
        return $this->typeDeclarations ??= $this->collectTypeDeclarations();
    }

    /**
     * @return list<TypeDeclaration>
     */
    private function collectTypeDeclarations(): array
    {
        $declarations = $this->standaloneTypes;

        foreach ($this->components as $component) {
            foreach ($component->blocks as $block) {
                if ($block->tag === 'script') {
                    $declarations = [...$declarations, ...TypeDeclaration::fromScript(new Script($block->content), $component->path, $component->source, $block->start)];
                }
            }
        }

        return $declarations;
    }

    // ---- TypeScript module space ----------------------------------------------
    // The `.ts` twin of the template selectors above. A module rule composes exactly what a template
    // rule does — a selector opens a query, `where`/`reject` narrow it, a terminal returns matches
    // that know their `file:line` — and the names mirror the backend's ({@see \JesseGall\CodeCommandments\Ast\Codebase})
    // so a rule about a function, a class or a call reads the same whichever language it judges.

    /**
     * Every FUNCTION — a `function` declaration and a class method alike, because a rule about what a
     * function is named or how its body is shaped does not care which form declared it.
     */
    public function whereFunction(): TsQuery
    {
        return $this->whereTsNode(static fn (TsNode $node): bool => $node instanceof FunctionDecl || $node instanceof MethodDecl);
    }

    /**
     * Every method declared in a class body.
     */
    public function whereMethodDeclaration(): TsQuery
    {
        return $this->whereTsNode(static fn (TsNode $node): bool => $node instanceof MethodDecl);
    }

    /**
     * Every `class` declaration.
     */
    public function whereClass(): TsQuery
    {
        return $this->whereTsNode(static fn (TsNode $node): bool => $node instanceof ClassDecl);
    }

    /**
     * Every `const`/`let`/`var` declaration.
     */
    public function whereVariable(): TsQuery
    {
        return $this->whereTsNode(static fn (TsNode $node): bool => $node instanceof VariableDecl);
    }

    /**
     * Every FIELD declared in a class body.
     */
    public function whereField(): TsQuery
    {
        return $this->whereTsNode(static fn (TsNode $node): bool => $node instanceof FieldDecl);
    }

    /**
     * Every parameter of every function, method and arrow.
     */
    public function whereParameter(): TsQuery
    {
        return $this->whereTsNode(static fn (TsNode $node): bool => $node instanceof Param);
    }

    /**
     * Every statement — a branch, a loop, a return, a throw, a bare expression.
     */
    public function whereStatement(): TsQuery
    {
        return $this->whereTsNode(static fn (TsNode $node): bool => $node instanceof Stmt);
    }

    /**
     * Every `if` — the selector a guard-clause or branch-depth rule opens on.
     */
    public function whereIf(): TsQuery
    {
        return $this->whereTsNode(static fn (TsNode $node): bool => $node instanceof IfStmt);
    }

    /**
     * Every `switch`.
     */
    public function whereSwitch(): TsQuery
    {
        return $this->whereTsNode(static fn (TsNode $node): bool => $node instanceof SwitchStmt);
    }

    /**
     * Open a query over every module node, narrowed by your own predicate.
     *
     * @param  Closure(TsNode): bool  $select
     */
    public function whereTsNode(Closure $select): TsQuery
    {
        return new TsQuery(fn () => $this->tsNodes(), $select);
    }

    /**
     * Every CALL in the codebase's TypeScript — `node.closest('[data-x]')`, `requestAnimationFrame(…)`.
     */
    public function whereCall(): ExprQuery
    {
        return $this->whereExpression(static fn (Expr $expr): bool => $expr->isCall());
    }

    /**
     * Every member READ — `node.dataset.kind`, `a?.b`.
     */
    public function whereMember(): ExprQuery
    {
        return $this->whereExpression(static fn (Expr $expr): bool => $expr->is(ExprKind::Member));
    }

    /**
     * Every `??` default — the absence fallback an absence rule opens on.
     */
    public function whereCoalesce(): ExprQuery
    {
        return $this->whereExpression(static fn (Expr $expr): bool => $expr->isCoalesce());
    }

    /**
     * Every ternary.
     */
    public function whereTernary(): ExprQuery
    {
        return $this->whereExpression(static fn (Expr $expr): bool => $expr->isTernary());
    }

    /**
     * Every string LITERAL in module space — the counterpart of {@see whereText}, for the words a
     * program decides with rather than the ones a user reads.
     */
    public function whereLiteral(): ExprQuery
    {
        return $this->whereExpression(static fn (Expr $expr): bool => $expr->is(ExprKind::Literal));
    }

    /**
     * Open a query over every expression, narrowed by your own predicate.
     *
     * @param  Closure(Expr): bool  $select
     */
    public function whereExpression(Closure $select): ExprQuery
    {
        return new ExprQuery(fn () => $this->tsExpressions(), $select);
    }

    /**
     * Every parsed module — a standalone `.ts` file, and every component's `<script>` block.
     *
     * @return list<TsModule>
     */
    public function modules(): array
    {
        return $this->modules ??= $this->collectModules();
    }

    /**
     * @return list<array{0: TsNode, 1: TsModule}>
     */
    private function tsNodes(): array
    {
        if ($this->tsNodes !== null) {
            return $this->tsNodes;
        }

        $pairs = [];

        foreach ($this->modules() as $module) {
            foreach ($module->nodes() as $node) {
                $pairs[] = [$node, $module];
            }
        }

        return $this->tsNodes = $pairs;
    }

    /**
     * @return list<array{0: Expr, 1: TsModule}>
     */
    private function tsExpressions(): array
    {
        if ($this->tsExpressions !== null) {
            return $this->tsExpressions;
        }

        $pairs = [];

        foreach ($this->modules() as $module) {
            foreach ($module->expressions() as $expression) {
                $pairs[] = [$expression, $module];
            }
        }

        return $this->tsExpressions = $pairs;
    }

    /**
     * @return list<TsModule>
     */
    private function collectModules(): array
    {
        $modules = [];

        foreach ($this->typeScript as $file => $source) {
            $modules = [...$modules, ...self::readable(static fn () => [TsModule::fromFile($source, $file)], $file)];
        }

        foreach ($this->components as $component) {
            foreach ($component->blocks as $block) {
                if ($block->tag === 'script') {
                    $modules = [...$modules, ...self::readable(
                        static fn () => [TsModule::fromBlock($block->content, $component->path, $component->source, $block->start)],
                        $component->path,
                    )];
                }
            }
        }

        return $modules;
    }

    /**
     * Every [element, component] pair across all templates — flattened once, cached.
     *
     * @return list<array{0: Element, 1: Sfc}>
     */
    public function nodes(): array
    {
        return $this->nodes ??= $this->flatten();
    }

    /**
     * @return list<array{0: Element, 1: Sfc}>
     */
    private function flatten(): array
    {
        $nodes = [];

        foreach ($this->components as $component) {
            self::collect($component->template, $component, $nodes);
        }

        return $nodes;
    }

    /**
     * @param  list<array{0: Element, 1: Sfc}>  $nodes
     */
    private static function collect(Element $node, Sfc $component, array &$nodes): void
    {
        foreach ($node->children as $child) {
            $nodes[] = [$child, $component];
            self::collect($child, $component, $nodes);
        }
    }

    /**
     * @return iterable<string>
     */
    private static function filesIn(string $path, string $extension, ExcludedPaths $excluded = new ExcludedPaths()): iterable
    {
        if (is_file($path)) {
            if (pathinfo($path, PATHINFO_EXTENSION) === $extension) {
                yield $path;
            }

            return;
        }

        if (! is_dir($path)) {
            return;
        }

        $directory = new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS);

        $pruned = new RecursiveCallbackFilterIterator($directory, static function (\SplFileInfo $file) use ($excluded): bool {
            if (! $file->isDir()) {
                return true;
            }

            return ! $file->isLink()
                && ! str_starts_with($file->getFilename(), '.')
                && ! in_array($file->getFilename(), ['vendor', 'node_modules'], true)
                && ! $excluded->covers($file->getPathname());
        });

        foreach (new RecursiveIteratorIterator($pruned) as $file) {
            if ($file->isFile() && $file->getExtension() === $extension) {
                yield $file->getPathname();
            }
        }
    }
}
