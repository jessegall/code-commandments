<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes\Php;

use Composer\Autoload\ClassLoader;
use JesseGall\CodeCommandments\Support\Pipes\MatchResult;
use JesseGall\CodeCommandments\Support\Pipes\Pipe;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

/**
 * Find call sites of the shape `(new X(...))(...)`.
 *
 * Every project-owned target is flagged. Vendor classes (where the
 * source file lives under `/vendor/`) are skipped because the consumer
 * can't refactor a third-party class.
 *
 * The match's `has_invoke_args` group encodes the severity hint:
 * `(new X(...))()` (no invocation args) is a clear-cut sin, while
 * `(new X(...))(arg, ...)` is a warning — the static factory might not
 * encode all invocation modes 1:1, so it's flagged for review rather
 * than failure.
 *
 * `static_names` (when non-empty) lists conventional public-static
 * helpers (`for`, `forget`, `flush`, `make`, `resolve`, `create`)
 * already declared on the target — used to make the suggestion concrete.
 *
 * @implements Pipe<PhpContext, PhpContext>
 */
final class FindInvokableConstructWithStaticHelper implements Pipe
{
    public const CONVENTIONAL_STATIC_NAMES = [
        'for', 'forget', 'flush', 'make', 'resolve', 'create',
    ];

    /** @var array<string, array{file: ?string, statics: list<string>}|false> */
    private static array $classCache = [];

    private static ?ClassLoader $composerLoader = null;

    private static bool $composerLoaderResolved = false;

    public function handle(mixed $input): mixed
    {
        if ($input->ast === null) {
            return $input->with(matches: []);
        }

        $localClasses = $this->collectLocalClasses($input->ast);
        $currentFileInVendor = str_contains($input->filePath, '/vendor/');

        $finder = new NodeFinder;

        /** @var array<Expr\FuncCall> $funcCalls */
        $funcCalls = $finder->findInstanceOf($input->ast, Expr\FuncCall::class);

        $matches = [];
        $seen = [];

        foreach ($funcCalls as $call) {
            if (! $call->name instanceof Expr\New_) {
                continue;
            }

            $new = $call->name;

            if (! $new->class instanceof Node\Name) {
                // Anonymous class or dynamic class — out of scope.
                continue;
            }

            $shortName = $new->class->getLast();
            $fqcn = $this->resolveFqcn($new->class, $input->useStatements, $input->namespace);

            $info = $this->resolveClassInfo($fqcn, $shortName, $localClasses, $input->filePath, $currentFileInVendor);

            if ($info['vendor']) {
                continue;
            }

            $hasInvokeArgs = ! empty($call->args);
            $line = $call->getStartLine();
            $dedupeKey = $shortName . '@' . $line . '@' . ($hasInvokeArgs ? '1' : '0');

            if (isset($seen[$dedupeKey])) {
                continue;
            }

            $seen[$dedupeKey] = true;

            $staticNames = $info['statics'];
            $staticHints = $staticNames === []
                ? ''
                : implode(', ', array_map(
                    static fn (string $n) => $shortName . '::' . $n . '()',
                    $staticNames,
                ));

            $matches[] = new MatchResult(
                name: 'invokable_construct',
                pattern: '',
                match: '(new ' . $shortName . '(...))(...)',
                line: $line,
                offset: null,
                content: $this->getSnippet($input->content, $line),
                groups: [
                    'class' => $shortName,
                    'fqcn' => $fqcn,
                    'has_invoke_args' => $hasInvokeArgs ? '1' : '0',
                    'static_names' => $staticHints,
                ],
            );
        }

        return $input->with(matches: $matches);
    }

    /**
     * @param  array<string, string>  $useStatements
     */
    private function resolveFqcn(Node\Name $name, array $useStatements, ?string $namespace): string
    {
        if ($name->isFullyQualified()) {
            return ltrim($name->toString(), '\\');
        }

        $parts = explode('\\', $name->toString());
        $first = $parts[0];

        if (isset($useStatements[$first])) {
            $parts[0] = $useStatements[$first];

            return implode('\\', $parts);
        }

        if ($namespace !== null && $namespace !== '') {
            return $namespace . '\\' . $name->toString();
        }

        return $name->toString();
    }

    /**
     * Resolve the target class to (a) whether it's vendored and (b) any
     * conventional static helpers already declared on it.
     *
     * @param  array<string, array{statics: list<string>}>  $localClasses
     * @return array{vendor: bool, statics: list<string>}
     */
    private function resolveClassInfo(
        string $fqcn,
        string $shortName,
        array $localClasses,
        string $currentFilePath,
        bool $currentFileInVendor,
    ): array {
        if (isset($localClasses[$shortName])) {
            return [
                'vendor' => $currentFileInVendor,
                'statics' => $localClasses[$shortName]['statics'],
            ];
        }

        if (array_key_exists($fqcn, self::$classCache)) {
            $cached = self::$classCache[$fqcn];

            if ($cached === false) {
                return ['vendor' => false, 'statics' => []];
            }

            return [
                'vendor' => $cached['file'] !== null && str_contains($cached['file'], '/vendor/'),
                'statics' => $cached['statics'],
            ];
        }

        $resolved = $this->resolveFromAutoload($fqcn);
        self::$classCache[$fqcn] = $resolved ?? false;

        if ($resolved === null) {
            return ['vendor' => false, 'statics' => []];
        }

        return [
            'vendor' => $resolved['file'] !== null && str_contains($resolved['file'], '/vendor/'),
            'statics' => $resolved['statics'],
        ];
    }

    /**
     * @return array{file: ?string, statics: list<string>}|null
     */
    private function resolveFromAutoload(string $fqcn): ?array
    {
        $loader = $this->getComposerLoader();

        if ($loader === null) {
            return null;
        }

        $file = $loader->findFile($fqcn);

        if ($file === false || ! is_file($file)) {
            return null;
        }

        $content = @file_get_contents($file);

        if ($content === false || $content === '') {
            return ['file' => $file, 'statics' => []];
        }

        try {
            $ast = (new ParserFactory)->createForNewestSupportedVersion()->parse($content);
        } catch (\Throwable) {
            return ['file' => $file, 'statics' => []];
        }

        if ($ast === null) {
            return ['file' => $file, 'statics' => []];
        }

        $parts = explode('\\', $fqcn);
        $short = array_pop($parts);
        $namespace = implode('\\', $parts);

        $classNode = $this->findClassNode($ast, $short, $namespace);

        if ($classNode === null) {
            return ['file' => $file, 'statics' => []];
        }

        return [
            'file' => $file,
            'statics' => $this->conventionalStaticMethods($classNode),
        ];
    }

    /**
     * @param  array<Node>  $ast
     */
    private function findClassNode(array $ast, string $short, string $namespace): ?Node\Stmt\Class_
    {
        foreach ($ast as $node) {
            if ($node instanceof Node\Stmt\Namespace_) {
                $ns = $node->name?->toString() ?? '';

                if ($ns !== $namespace) {
                    continue;
                }

                foreach ($node->stmts as $stmt) {
                    if ($stmt instanceof Node\Stmt\Class_ && $stmt->name?->toString() === $short) {
                        return $stmt;
                    }
                }
            } elseif ($node instanceof Node\Stmt\Class_ && $namespace === '' && $node->name?->toString() === $short) {
                return $node;
            }
        }

        return null;
    }

    /**
     * Walk every class in the AST and capture short name → static helper names.
     *
     * @param  array<Node>  $ast
     * @return array<string, array{statics: list<string>}>
     */
    private function collectLocalClasses(array $ast): array
    {
        $finder = new NodeFinder;

        /** @var array<Node\Stmt\Class_> $classes */
        $classes = $finder->findInstanceOf($ast, Node\Stmt\Class_::class);

        $out = [];

        foreach ($classes as $class) {
            if ($class->name === null) {
                continue;
            }

            $out[$class->name->toString()] = [
                'statics' => $this->conventionalStaticMethods($class),
            ];
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function conventionalStaticMethods(Node\Stmt\Class_ $class): array
    {
        $out = [];

        foreach ($class->getMethods() as $method) {
            if (! $method->isStatic() || ! $method->isPublic()) {
                continue;
            }

            $name = $method->name->toString();

            if (in_array($name, self::CONVENTIONAL_STATIC_NAMES, true)) {
                $out[] = $name;
            }
        }

        return $out;
    }

    private function getComposerLoader(): ?ClassLoader
    {
        if (self::$composerLoaderResolved) {
            return self::$composerLoader;
        }

        self::$composerLoaderResolved = true;

        foreach (spl_autoload_functions() ?: [] as $autoload) {
            if (is_array($autoload) && isset($autoload[0]) && $autoload[0] instanceof ClassLoader) {
                self::$composerLoader = $autoload[0];

                return self::$composerLoader;
            }
        }

        return null;
    }

    private function getSnippet(string $content, int $line): string
    {
        $lines = explode("\n", $content);

        return isset($lines[$line - 1]) ? trim($lines[$line - 1]) : '';
    }
}
