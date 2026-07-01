<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Hints;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Cli\Scope\Scope;
use JesseGall\CodeCommandments\Scribes\Edit;
use JesseGall\CodeCommandments\Scribes\Scribe;
use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\UnionType;
use PhpParser\NodeFinder;

/**
 * Brings a Spatie `Data` class's magic surface in line with the spatie-data skill.
 * It does three things, returning the new content of every changed file:
 *
 *  1. Renames a `public static` object factory that builds an instance of itself
 *     but isn't `from…`-named (`forCredential(Credential $c)`) to `from<Type>`
 *     (`fromCredential`), so `::from()` can dispatch to it — and rewrites its call
 *     sites to `::from(...)`.
 *  2. Regenerates the class docblock's `@method` lines: one `@method static static
 *     from(<params>)` per object factory (documenting the magic overload, never the
 *     concrete name), PLUS an `@method static static from(array $payload)` so the raw
 *     array payload stays accepted (the typed lines are additive overloads, not a
 *     narrowing) — replacing any existing `@method` lines (so a collision line is
 *     fixed in passing).
 *  3. Adds the shape-preserving conditional `@method … collect(iterable $items)`
 *     when the class is actually `::collect()`-ed somewhere.
 *
 * It reads each file's source from disk (by the parsed path) and returns
 * `path => newContent` for the files it changed; the caller writes or diffs them.
 */
final class DataHintScribe extends Scribe
{
    private const string DATA = 'Spatie\\LaravelData\\Data';

    private const string COLLECT_HINT =
        '@method static ($items is \Illuminate\Support\Collection ? \Illuminate\Support\Collection<int, static> : array<int, static>) collect(iterable $items)';

    /**
     * Rewrite the magic surface of every Data class under the codebase, returning
     * `path => newContent` for changed files.
     *
     * A scoped run (`$scope->isScoped()` — the working-tree/branch changes) runs in
     * **docblock-only** mode: it edits ONLY the scoped files and does NOT rename
     * factories or rewrite call sites, because a rename's call sites can live in files
     * outside the scope that a partial run can't see. A scoped run therefore documents
     * only already-`from…` (dispatchable) factories and leaves a mis-prefixed one for a
     * whole-tree run.
     *
     * @return array<string, string>  path => new file content, for changed files only
     */
    public function rewrites(Codebase $codebase, Scope $scope): array
    {
        $docblockOnly = $scope->isScoped();
        $classes = $this->dataClasses($codebase);
        [$collectUsed, $calls] = $this->scanCalls($codebase);

        $renames = $docblockOnly ? [] : $this->planRenames($classes);

        /** @var array<string, list<Edit>> $editsByFile */
        $editsByFile = [];

        foreach ($classes as $fqcn => $class) {
            if ($docblockOnly && ! $scope->includes($class->file)) {
                continue;
            }

            $source = $codebase->sourceOf($class->file) ?? '';

            if (! $docblockOnly) {
                foreach ($class->factories as $factory) {
                    $newName = $renames["{$fqcn}\0{$factory->name}"] ?? null;

                    if ($newName !== null) {
                        $editsByFile[$class->file][] = $this->replaceNode($factory->nameNode, $newName);
                    }
                }
            }

            $edit = $this->docblockEdit($class->node, $source, $this->methodLines($class, $source, $collectUsed[$fqcn] ?? false, $docblockOnly));

            if ($edit !== null) {
                $editsByFile[$class->file][] = $edit;
            }
        }

        if (! $docblockOnly) {
            $sources = [];

            foreach ($calls as $call) {
                if (! isset($renames["{$call->class}\0{$call->method}"])) {
                    continue;
                }

                $source = $sources[$call->file] ??= $codebase->sourceOf($call->file) ?? '';
                $editsByFile[$call->file][] = $this->rewriteCall($call, $source);
            }
        }

        $changed = [];

        foreach ($editsByFile as $file => $edits) {
            $source = $codebase->sourceOf($file) ?? '';
            $new = $this->applyEdits($source, $edits);

            if ($new !== $source) {
                $changed[$file] = $new;
            }
        }

        return $changed;
    }

    /**
     * The edit for one renamed factory's call site. The factory takes one parameter, so the
     * call carries a single argument that `from()` dispatches by type: a positional arg is a
     * name-only `make($x)` → `from($x)` swap, and a NAMED arg drops its name (`from(name: $x)`
     * is invalid) to become positional `from($x)`.
     */
    private function rewriteCall(CallSite $call, string $source): Edit
    {
        $args = $call->node->args;

        if (count($args) === 1 && $args[0] instanceof Node\Arg && $args[0]->name !== null) {
            $value = $this->slice($source, $args[0]->value);
            $class = $this->slice($source, $call->node->class);

            return $this->replaceNode($call->node, "{$class}::from({$value})");
        }

        return $this->replaceNode($call->nameNode, 'from');
    }

    private function slice(string $source, Node $node): string
    {
        return substr($source, $node->getStartFilePos(), $node->getEndFilePos() + 1 - $node->getStartFilePos());
    }

    /**
     * @param  array<string, DataClass>  $classes
     * @return array<string, string>  "fqcn\0oldName" => newName
     */
    private function planRenames(array $classes): array
    {
        $renames = [];

        foreach ($classes as $fqcn => $class) {
            $taken = array_map(static fn (ClassMethod $m): string => strtolower($m->name->toString()), $class->node->getMethods());

            foreach ($class->factories as $factory) {
                if ($factory->isFrom) {
                    continue;
                }

                $new = $this->deriveFromName($factory->params, $taken);

                if ($new !== null) {
                    $renames["{$fqcn}\0{$factory->name}"] = $new;
                    $taken[] = strtolower($new);
                }
            }
        }

        return $renames;
    }

    /**
     * `from` + the short name of the first parameter's type — `Credential $c` →
     * `fromCredential`, `string $code` → `fromString`. Null when it can't be derived
     * or the target name is already taken (leave that factory alone rather than
     * collide).
     *
     * @param  list<Node\Param>  $params
     * @param  list<string>  $taken  lowercased method names already on the class
     */
    private function deriveFromName(array $params, array $taken): ?string
    {
        $short = $this->typeShortName($params[0]->type ?? null);

        if ($short === null) {
            return null;
        }

        $name = 'from' . ucfirst($short);

        return in_array(strtolower($name), $taken, true) ? null : $name;
    }

    /**
     * The `@method` lines a class should carry: one `from(<params>)` per object
     * factory, plus the conditional `collect()` line when the class is collected.
     *
     * @return list<string>
     */
    private function methodLines(DataClass $class, string $source, bool $collectUsed, bool $docblockOnly): array
    {
        $lines = [];

        foreach ($class->factories as $factory) {
            // Scoped (docblock-only) runs don't rename, so only an already-`from…`
            // factory is dispatchable — documenting a mis-prefixed one would lie.
            if ($docblockOnly && ! $factory->isFrom) {
                continue;
            }

            $lines[] = "@method static static from({$this->paramsSource($factory->params, $source)})";
        }

        // `Data::from()` ALWAYS accepts the raw array payload as well — emit it as an extra
        // overload so the typed factory line(s) stay ADDITIVE: a `from(['x' => …])` call isn't
        // flagged by an IDE that would otherwise read the typed line as from()'s only signature.
        if ($lines !== []) {
            $lines[] = '@method static static from(array $payload)';
        }

        if ($collectUsed) {
            $lines[] = self::COLLECT_HINT;
        }

        return $lines;
    }

    /**
     * The original source of a factory's parameter list (between the first and last
     * param), so the generated `@method` mirrors exactly how the params were spelled.
     *
     * @param  list<Node\Param>  $params
     */
    private function paramsSource(array $params, string $source): string
    {
        $first = $params[0];
        $last = $params[count($params) - 1];
        $start = $first->getStartFilePos();
        $end = $last->getEndFilePos();

        return substr($source, $start, $end - $start + 1);
    }

    /**
     * Build the edit that rewrites the class docblock: strip the existing `@method`
     * lines and inject the freshly computed ones, preserving the prose. When there
     * is no docblock, synthesise one above the class.
     *
     * @param  list<string>  $methodLines
     * @return Edit|null  null when there is nothing to write
     */
    private function docblockEdit(Class_ $class, string $source, array $methodLines): ?Edit
    {
        $doc = $class->getDocComment();
        $indent = $this->indentOf($class, $source);

        if ($doc === null) {
            if ($methodLines === []) {
                return null;
            }

            $insertAt = $this->lineStart($source, $class->getStartFilePos());
            $block = "{$indent}/**\n";

            foreach ($methodLines as $line) {
                $block .= "{$indent} * {$line}\n";
            }

            $block .= "{$indent} */\n";

            // A pure insertion replaces nothing: end before start so no char is eaten.
            return new Edit($insertAt, $insertAt - 1, $block);
        }

        $kept = [];

        foreach (preg_split('/\R/', $doc->getText()) ?: [] as $line) {
            if (preg_match('/^\s*\*?\s*@method\b/', $line) !== 1) {
                $kept[] = $line;
            }
        }

        // $kept still ends with the closing ` */` line; inject before it.
        $closing = array_pop($kept);

        if ($methodLines !== [] && $kept !== [] && trim((string) end($kept), " \t*") !== '') {
            $kept[] = "{$indent} *";
        }

        foreach ($methodLines as $line) {
            $kept[] = "{$indent} * {$line}";
        }

        $kept[] = $closing;

        return new Edit($doc->getStartFilePos(), $doc->getEndFilePos(), implode("\n", $kept));
    }

    /**
     * @return array<string, DataClass>  FQCN => the Data class
     */
    private function dataClasses(Codebase $codebase): array
    {
        $classes = [];
        $finder = new NodeFinder;

        foreach ($codebase->files() as $file) {
            foreach ($finder->findInstanceOf($file->ast, Class_::class) as $class) {
                /** @var Class_ $class */
                $fqcn = ($class->namespacedName ?? null)?->toString();

                if ($fqcn === null || ! $codebase->extends($fqcn, self::DATA)) {
                    continue;
                }

                $factories = [];

                foreach ($class->getMethods() as $method) {
                    if ($this->isObjectFactory($method, $fqcn)) {
                        $name = $method->name->toString();
                        $factories[] = new Factory(
                            $name,
                            $method->name,
                            array_values($method->params),
                            str_starts_with($name, 'from') && $name !== 'from',
                        );
                    }
                }

                $classes[$fqcn] = new DataClass($file->path, $class, $factories);
            }
        }

        return $classes;
    }

    /**
     * A public-static method that returns an instance of its own class and builds one in its
     * body (`self::from(...)` / `new self(...)`) from EXACTLY ONE parameter. The single
     * parameter is the whole point: `from()` dispatches by one argument's type, so a
     * `from($source)` call can route to it. A multi-parameter method is a named constructor
     * (`compose($a, $b, $c)`, `make($a, …, $p)`) — it can't be reached through `from()` and is
     * left alone.
     */
    private function isObjectFactory(ClassMethod $method, string $fqcn): bool
    {
        if (! $method->isPublic() || ! $method->isStatic() || count($method->params) !== 1) {
            return false;
        }

        return $this->returnsSelf($method, $fqcn) && $this->constructsSelf($method);
    }

    private function returnsSelf(ClassMethod $method, string $fqcn): bool
    {
        $type = $method->getReturnType();

        if ($type instanceof Identifier) {
            return in_array($type->toLowerString(), ['self', 'static'], true);
        }

        return $type instanceof Name
            && (in_array($type->toLowerString(), ['self', 'static'], true) || ltrim($type->toString(), '\\') === $fqcn);
    }

    private function constructsSelf(ClassMethod $method): bool
    {
        if ($method->stmts === null) {
            return false;
        }

        $finder = new NodeFinder;

        foreach ($finder->findInstanceOf($method->stmts, New_::class) as $new) {
            if ($new->class instanceof Name && in_array($new->class->toLowerString(), ['self', 'static'], true)) {
                return true;
            }
        }

        foreach ($finder->findInstanceOf($method->stmts, StaticCall::class) as $call) {
            if ($call->class instanceof Name
                && in_array($call->class->toLowerString(), ['self', 'static'], true)
                && $call->name instanceof Identifier
                && $call->name->toString() === 'from') {
                return true;
            }
        }

        return false;
    }

    /**
     * Scan every static call once: record which classes are `::collect()`-ed, and
     * every `Class::method(` site (resolved class FQCN) for the call-site rewrite.
     *
     * @return array{0: array<string, true>, 1: list<CallSite>}
     */
    private function scanCalls(Codebase $codebase): array
    {
        $collectUsed = [];
        $calls = [];
        $finder = new NodeFinder;

        foreach ($codebase->files() as $file) {
            foreach ($finder->findInstanceOf($file->ast, StaticCall::class) as $call) {
                if (! $call->name instanceof Identifier) {
                    continue;
                }

                $class = $this->callClass($call);

                if ($class === null) {
                    continue;
                }

                $method = $call->name->toString();

                if ($method === 'collect') {
                    $collectUsed[$class] = true;
                }

                $calls[] = new CallSite($class, $method, $call->name, $file->path, $call);
            }
        }

        return [$collectUsed, $calls];
    }

    /**
     * The resolved class FQCN a static call targets, resolving `self`/`static` to the
     * enclosing class. Null for a dynamic class expression.
     */
    private function callClass(StaticCall $call): ?string
    {
        if (! $call->class instanceof Name) {
            return null;
        }

        if (in_array($call->class->toLowerString(), ['self', 'static'], true)) {
            $enclosing = $this->enclosingClass($call);

            return $enclosing === null ? null : ($enclosing->namespacedName ?? null)?->toString();
        }

        return ltrim($call->class->toString(), '\\');
    }

    private function enclosingClass(Node $node): ?Class_
    {
        $parent = $node->getAttribute('parent');

        while ($parent instanceof Node) {
            if ($parent instanceof Class_) {
                return $parent;
            }

            $parent = $parent->getAttribute('parent');
        }

        return null;
    }

    private function typeShortName(?Node $type): ?string
    {
        if ($type instanceof NullableType) {
            return $this->typeShortName($type->type);
        }

        if (($type instanceof UnionType || $type instanceof IntersectionType) && $type->types !== []) {
            return $this->typeShortName($type->types[0]);
        }

        if ($type instanceof Identifier) {
            return ucfirst($type->toString());
        }

        if ($type instanceof Name) {
            return $type->getLast();
        }

        return null;
    }

    private function indentOf(Class_ $class, string $source): string
    {
        $start = $this->lineStart($source, $class->getStartFilePos());

        return substr($source, $start, $class->getStartFilePos() - $start);
    }

    private function lineStart(string $source, int $offset): int
    {
        $newline = strrpos(substr($source, 0, $offset), "\n");

        return $newline === false ? 0 : $newline + 1;
    }
}
