<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use JesseGall\PhpTypes\T_String;

/**
 * Censuses tuples of arguments passed TOGETHER across a project's call sites — the
 * raw material for spotting a data clump (the same >= 3 values threaded through many
 * calls want to be one value object). Standalone + cached per composer.json root
 * (scans `src/` and/or `app/`), like {@see ConfigMapIndex}.
 *
 * A tuple is the SORTED SET of simple argument tokens at a call with >= 3 of them —
 * a `$var` or a `$this->prop` (anything else makes the call non-simple and skipped),
 * so reordered passings of the same values collapse to one clump. Backs
 * DataClumpToValueObjectProphet (#163 #9). Pure AST.
 */
final class ArgumentGroupCensus
{
    /** @var array<string, self> */
    private static array $cache = [];

    private const MIN_ARITY = 3;

    /**
     * @param  array<string, array{names: list<string>, sites: int, files: array<string, true>}>  $clumps  tuple key → record
     */
    private function __construct(private readonly array $clumps) {}

    public static function forFile(string $filePath): self
    {
        $root = self::locateRoot($filePath);

        if ($root === null) {
            return new self([]);
        }

        return self::$cache[$root] ??= self::scan($root);
    }

    public static function flush(): void
    {
        self::$cache = [];
    }

    public function isEmpty(): bool
    {
        return $this->clumps === [];
    }

    /** The sorted-set key for a list of argument tokens. */
    public static function keyFor(array $names): string
    {
        $names = array_values(array_unique($names));
        sort($names);

        return implode(',', $names);
    }

    /**
     * The clump key for a call's arguments — the sorted set of >= MIN_ARITY distinct
     * simple tokens (`$var` / `$this->prop`), or null when the call is not a simple
     * multi-arg passing. The single source of truth shared by the census and the prophet.
     *
     * @param  list<Node\Arg>  $args
     */
    public static function keyForArgs(array $args): ?string
    {
        $tokens = self::simpleArgTokens($args);

        if ($tokens === null) {
            return null;
        }

        $names = array_values(array_unique($tokens));

        return count($names) < self::MIN_ARITY ? null : self::keyFor($names);
    }

    /** Whether this tuple travels together at >= $minSites call sites spanning >= $minFiles files. */
    public function isClump(string $key, int $minSites, int $minFiles): bool
    {
        $clump = $this->clumps[$key] ?? null;

        return $clump !== null && $clump['sites'] >= $minSites && count($clump['files']) >= $minFiles;
    }

    public function siteCount(string $key): int
    {
        return $this->clumps[$key]['sites'] ?? 0;
    }

    public function fileCount(string $key): int
    {
        return count($this->clumps[$key]['files'] ?? []);
    }

    /** The tuple's member tokens, in sorted order. @return list<string> */
    public function membersOf(string $key): array
    {
        return $this->clumps[$key]['names'] ?? [];
    }

    private static function locateRoot(string $filePath): ?string
    {
        $dir = \dirname($filePath);
        $previous = T_String::empty();

        while ($dir !== $previous && $dir !== '' && $dir !== '.') {
            if (is_file($dir . '/composer.json') && (is_dir($dir . '/src') || is_dir($dir . '/app'))) {
                return $dir;
            }

            $previous = $dir;
            $dir = \dirname($dir);
        }

        return null;
    }

    private static function scan(string $root): self
    {
        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $finder = new NodeFinder;
        $clumps = [];

        foreach (['src', 'app'] as $sub) {
            $dir = $root . '/' . $sub;

            if (! is_dir($dir)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if (! $file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                    continue;
                }

                try {
                    $ast = $parser->parse((string) file_get_contents($file->getPathname()));
                } catch (\Throwable) {
                    continue;
                }

                if ($ast === null) {
                    continue;
                }

                self::collectCalls($finder, $ast, $file->getPathname(), $clumps);
            }
        }

        return new self($clumps);
    }

    /**
     * @param  array<Node>  $ast
     * @param  array<string, array{names: list<string>, sites: int, files: array<string, true>}>  $clumps
     */
    private static function collectCalls(NodeFinder $finder, array $ast, string $path, array &$clumps): void
    {
        // NOTE: `new X(...)` is deliberately excluded — constructing a value object
        // FROM the parts is the GOAL, not the clump; and constructor DI ($apiKey, $http,
        // $logger) is wiring, not data. The clump is the loose parts threaded through CALLS.
        foreach ([Node\Expr\MethodCall::class, Node\Expr\StaticCall::class, Node\Expr\FuncCall::class] as $type) {
            foreach ($finder->findInstanceOf($ast, $type) as $call) {
                if (method_exists($call, 'isFirstClassCallable') && $call->isFirstClassCallable()) {
                    continue;
                }

                $key = self::keyForArgs($call->getArgs());

                if ($key === null) {
                    continue;
                }

                if (! isset($clumps[$key])) {
                    $clumps[$key] = ['names' => explode(',', $key), 'sites' => 0, 'files' => []];
                }

                $clumps[$key]['sites']++;
                $clumps[$key]['files'][$path] = true;
            }
        }
    }

    /**
     * The simple tokens of an arg list, or null if the call has < MIN_ARITY args or
     * any argument is not a plain `$var` / `$this->prop`.
     *
     * @param  list<Node\Arg>  $args
     * @return list<string>|null
     */
    private static function simpleArgTokens(array $args): ?array
    {
        if (count($args) < self::MIN_ARITY) {
            return null;
        }

        $tokens = [];

        foreach ($args as $arg) {
            if (! $arg instanceof Node\Arg || $arg->unpack) {
                return null;
            }

            $value = $arg->value;

            if ($value instanceof Node\Expr\Variable && is_string($value->name)) {
                $tokens[] = '$' . $value->name;
            } elseif ($value instanceof Node\Expr\PropertyFetch
                && $value->var instanceof Node\Expr\Variable
                && $value->var->name === 'this'
                && $value->name instanceof Node\Identifier
            ) {
                $tokens[] = 'this->' . $value->name->toString();
            } else {
                return null;
            }
        }

        return $tokens;
    }
}
