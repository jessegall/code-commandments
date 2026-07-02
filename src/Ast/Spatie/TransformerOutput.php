<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Spatie;

use JesseGall\CodeCommandments\Ast\Codebase;
use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\MagicConst;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeFinder;

/**
 * Where `spatie/laravel-typescript-transformer` writes the frontend types it GENERATES
 * from `#[TypeScript]` classes — read from the project's own configuration, never
 * assumed. Two forms are understood, each resolved to the exact path it covers:
 *
 *   - the v3 fluent provider — `->outputDirectory(<expr>)` in a
 *     `TypeScriptTransformerApplicationServiceProvider` — resolves to that DIRECTORY
 *     (a writer drops one or many generated files into it); and
 *   - the classic config array — `'output_file' => <expr>` in
 *     `config/typescript-transformer.php` — resolves to that single FILE.
 *
 * The path `<expr>` is evaluated statically: string literals, `.` concatenation,
 * `dirname(__DIR__, n)` / `__DIR__` resolved from the file's own location, the Laravel
 * `*_path()` helpers resolved from the project root, and a local `$var` traced to its
 * assignment. An expression it can't evaluate yields null — better no exemption than a
 * wrong one.
 */
final class TransformerOutput
{
    /**
     * The absolute path generated types are written to — an output DIRECTORY (fluent
     * form) or a single output FILE (config form) — or null when the project declares no
     * transformer output this reader can resolve. Both are compared the same way by
     * {@see \JesseGall\CodeCommandments\Bridge\GeneratedTypes}: a file matches exactly, a
     * directory matches its descendants.
     */
    public static function locationIn(Codebase $codebase): ?string
    {
        foreach ($codebase->files() as $file) {
            $finder = new NodeFinder;
            $assignments = self::assignments($finder, $file->ast);
            $path = realpath($file->path) ?: $file->path;

            foreach ($finder->findInstanceOf($file->ast, MethodCall::class) as $call) {
                if ($call->name instanceof Identifier
                    && $call->name->toString() === 'outputDirectory'
                    && isset($call->args[0])) {
                    $directory = self::evaluate($call->args[0]->value, $path, $assignments);

                    if ($directory !== null) {
                        return self::normalise($directory);
                    }
                }
            }

            $outputFile = self::configuredOutputFile($finder, $file->ast, $path, $assignments);

            if ($outputFile !== null) {
                return self::normalise($outputFile);
            }
        }

        return null;
    }

    /**
     * The resolved `output_file` value of a `config/typescript-transformer.php` array.
     *
     * @param  array<string, Node>  $assignments
     * @param  list<Node>  $ast
     */
    private static function configuredOutputFile(NodeFinder $finder, array $ast, string $path, array $assignments): ?string
    {
        foreach ($finder->findInstanceOf($ast, Node\ArrayItem::class) as $item) {
            if ($item->key instanceof String_ && $item->key->value === 'output_file') {
                return self::evaluate($item->value, $path, $assignments);
            }
        }

        return null;
    }

    /**
     * The file's top-level `$name = <expr>` assignments, so a path built from a local
     * (`$root = dirname(__DIR__, 3)`) resolves.
     *
     * @param  list<Node>  $ast
     * @return array<string, Node>
     */
    private static function assignments(NodeFinder $finder, array $ast): array
    {
        $map = [];

        foreach ($finder->findInstanceOf($ast, Assign::class) as $assign) {
            if ($assign->var instanceof Variable && is_string($assign->var->name)) {
                $map[$assign->var->name] = $assign->expr;
            }
        }

        return $map;
    }

    /**
     * @param  array<string, Node>  $assignments
     */
    private static function evaluate(Node $expr, string $file, array $assignments): ?string
    {
        if ($expr instanceof String_) {
            return $expr->value;
        }

        if ($expr instanceof Concat) {
            $left = self::evaluate($expr->left, $file, $assignments);
            $right = self::evaluate($expr->right, $file, $assignments);

            return $left === null || $right === null ? null : $left . $right;
        }

        if ($expr instanceof Variable && is_string($expr->name)) {
            $bound = $assignments[$expr->name] ?? null;

            return $bound === null ? null : self::evaluate($bound, $file, $assignments);
        }

        if ($expr instanceof MagicConst\Dir) {
            return dirname($file);
        }

        if ($expr instanceof MagicConst\File) {
            return $file;
        }

        if ($expr instanceof FuncCall && $expr->name instanceof Node\Name) {
            return self::evaluateCall($expr, $file, $assignments);
        }

        return null;
    }

    /**
     * @param  array<string, Node>  $assignments
     */
    private static function evaluateCall(FuncCall $call, string $file, array $assignments): ?string
    {
        $name = $call->name->toString();

        if ($name === 'dirname' && isset($call->args[0])) {
            $base = self::evaluate($call->args[0]->value, $file, $assignments);
            $levels = isset($call->args[1]) && $call->args[1]->value instanceof Node\Scalar\Int_
                ? $call->args[1]->value->value
                : 1;

            return $base === null ? null : dirname($base, max(1, $levels));
        }

        // The Laravel path helpers — resolved from the project root (the nearest ancestor
        // with a composer.json), so `resource_path('js/types')` becomes an absolute dir.
        $roots = ['base_path' => '', 'resource_path' => 'resources', 'app_path' => 'app', 'config_path' => 'config', 'storage_path' => 'storage'];

        if (array_key_exists($name, $roots)) {
            $root = self::projectRoot($file);
            $suffix = isset($call->args[0]) ? self::evaluate($call->args[0]->value, $file, $assignments) : '';

            if ($root === null || $suffix === null) {
                return null;
            }

            return rtrim($root . '/' . $roots[$name] . '/' . ltrim($suffix, '/'), '/');
        }

        return null;
    }

    private static function projectRoot(string $file): ?string
    {
        $dir = dirname($file);

        while ($dir !== '/' && $dir !== '' && $dir !== '.') {
            if (is_file($dir . '/composer.json')) {
                return $dir;
            }

            $dir = dirname($dir);
        }

        return null;
    }

    /**
     * Collapse `.`/`..` segments to an absolute, comparable path — without realpath, so
     * a configured-but-not-yet-generated directory still resolves.
     */
    private static function normalise(string $path): string
    {
        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        return '/' . implode('/', $segments);
    }
}
