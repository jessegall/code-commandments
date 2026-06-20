<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

/**
 * Records which CONSUMER (a callee name + argument position) a `config('path')` read
 * is passed into, across a project's source — e.g. `->onQueue(config('queue.x'))`
 * registers `onqueue@0 → queue.x`. Standalone + cached per composer.json root (scans
 * `src/`/`app/`), like {@see ConfigMapIndex}.
 *
 * Backs HardcodedLiteralShouldBeConfigProphet (#163 #11): a hardcoded literal is only
 * a "should be config" finding when the SAME consumer also receives a `config()` read
 * of the path whose value equals that literal — i.e. the codebase reads it from config
 * in one place and hardcodes it in another (provable drift), not a coincidental value
 * match. Pure AST.
 */
final class ConfigConsumerCensus
{
    /** @var array<string, self> */
    private static array $cache = [];

    /**
     * @param  array<string, array<string, true>>  $consumerReads  "callee@argPos" → set of config paths read into it
     */
    private function __construct(private readonly array $consumerReads) {}

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
        return $this->consumerReads === [];
    }

    /** Whether some call site passes `config($path)` as argument #$argPos to a call to $callee. */
    public function readsPath(string $callee, int $argPos, string $path): bool
    {
        return isset($this->consumerReads[strtolower($callee) . '@' . $argPos][strtolower($path)]);
    }

    /** The consumer key for a call node (`calleeName@` is appended with the arg position by callers). */
    public static function calleeName(Node $call): ?string
    {
        if (($call instanceof Node\Expr\MethodCall || $call instanceof Node\Expr\NullsafeMethodCall || $call instanceof Node\Expr\StaticCall)
            && $call->name instanceof Node\Identifier
        ) {
            return strtolower($call->name->toString());
        }

        if ($call instanceof Node\Expr\FuncCall && $call->name instanceof Node\Name) {
            return strtolower($call->name->getLast());
        }

        return null;
    }

    private static function locateRoot(string $filePath): ?string
    {
        $dir = \dirname($filePath);
        $previous = '';

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
        $reads = [];

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

                self::collect($finder, $ast, $reads);
            }
        }

        return new self($reads);
    }

    /**
     * @param  array<Node>  $ast
     * @param  array<string, array<string, true>>  $reads
     */
    private static function collect(NodeFinder $finder, array $ast, array &$reads): void
    {
        foreach ([Node\Expr\MethodCall::class, Node\Expr\NullsafeMethodCall::class, Node\Expr\StaticCall::class, Node\Expr\FuncCall::class] as $type) {
            foreach ($finder->findInstanceOf($ast, $type) as $call) {
                if (method_exists($call, 'isFirstClassCallable') && $call->isFirstClassCallable()) {
                    continue;
                }

                $callee = self::calleeName($call);

                if ($callee === null || $callee === 'config') {
                    continue;
                }

                foreach ($call->getArgs() as $i => $arg) {
                    $path = self::configPathArg($arg->value);

                    if ($path !== null) {
                        $reads[$callee . '@' . $i][strtolower($path)] = true;
                    }
                }
            }
        }
    }

    /** The literal path of a `config('path')` read, else null. */
    private static function configPathArg(Node $value): ?string
    {
        if (! $value instanceof Node\Expr\FuncCall || ! $value->name instanceof Node\Name
            || strtolower($value->name->toString()) !== 'config'
        ) {
            return null;
        }

        $arg = $value->getArgs()[0]->value ?? null;

        return $arg instanceof Node\Scalar\String_ ? $arg->value : null;
    }
}
