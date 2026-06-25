<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast;

use FilesystemIterator;
use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\ParserFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The entry point to the query engine: parses a set of files (names resolved,
 * parents linked) and opens fluent {@see Query} builders over them. Each
 * `where*()` selects a kind of node; chain filters on the returned Query.
 */
final class Codebase
{
    /**
     * @param  list<ParsedFile>  $files
     */
    private function __construct(private readonly array $files) {}

    public static function scan(string ...$paths): self
    {
        $files = [];

        foreach ($paths as $path) {
            foreach (self::phpFilesIn($path) as $file) {
                $code = @file_get_contents($file);

                if ($code !== false) {
                    $files[] = self::parse($code, $file);
                }
            }
        }

        return new self($files);
    }

    public static function fromString(string $code, string $path = 'memory.php'): self
    {
        return new self([self::parse($code, $path)]);
    }

    /**
     * `$obj->method(...)` calls named one of $names (any, when empty).
     */
    public function whereMethod(string ...$names): Query
    {
        return new Query($this, static fn (Node $node): bool =>
            ($node instanceof MethodCall || $node instanceof NullsafeMethodCall)
            && $node->name instanceof Identifier
            && ($names === [] || in_array($node->name->toString(), $names, true)));
    }

    /**
     * `Class::method(...)` calls named one of $names.
     */
    public function whereStaticCall(string ...$names): Query
    {
        return new Query($this, static fn (Node $node): bool =>
            $node instanceof StaticCall
            && $node->name instanceof Identifier
            && ($names === [] || in_array($node->name->toString(), $names, true)));
    }

    /**
     * `function(...)` calls named one of $names.
     */
    public function whereFunction(string ...$names): Query
    {
        return new Query($this, static fn (Node $node): bool =>
            $node instanceof FuncCall
            && $node->name instanceof Name
            && ($names === [] || in_array($node->name->toString(), $names, true)));
    }

    /**
     * `new X(...)`, optionally only of the given fully-qualified class.
     */
    public function whereNew(?string $class = null): Query
    {
        $want = $class === null ? null : ltrim($class, '\\');

        return new Query($this, static fn (Node $node): bool =>
            $node instanceof New_
            && ($want === null || ($node->class instanceof Name && $node->class->toString() === $want)));
    }

    /**
     * `#[Attr(...)]` usages, matched by short name or fully-qualified name.
     */
    public function whereAttribute(string $name): Query
    {
        $want = ltrim($name, '\\');

        return new Query($this, static function (Node $node) use ($want): bool {
            if (! $node instanceof Attribute) {
                return false;
            }

            $resolved = $node->name->toString();

            return $resolved === $want || self::shortName($resolved) === $want;
        });
    }

    /**
     * Raw escape hatch: select nodes by your own predicate.
     *
     * @param  \Closure(Node): bool  $test
     */
    public function where(\Closure $test): Query
    {
        return new Query($this, $test);
    }

    /**
     * @return list<ParsedFile>
     */
    public function files(): array
    {
        return $this->files;
    }

    private static function shortName(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }

    private static function parse(string $code, string $path): ParsedFile
    {
        $ast = (new ParserFactory)->createForNewestSupportedVersion()->parse($code) ?? [];

        $traverser = new NodeTraverser(new NameResolver, new ParentConnectingVisitor);

        return new ParsedFile($path, $traverser->traverse($ast));
    }

    /**
     * @return iterable<string>
     */
    private static function phpFilesIn(string $path): iterable
    {
        if (is_file($path)) {
            yield $path;

            return;
        }

        if (! is_dir($path)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                yield $file->getPathname();
            }
        }
    }
}
