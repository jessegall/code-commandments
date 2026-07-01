<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast;

use JesseGall\CodeCommandments\Ast\Support\ReceiverResolver;
use JesseGall\CodeCommandments\Query as BaseQuery;
use PhpParser\Node;

/**
 * The backend fluent query over the PHP AST — the shared {@see BaseQuery} (`where`/`reject`, the
 * filter loop, decorator injection) plus the backend selectors and the three engine hooks:
 * candidates come from the codebase's bucketed-by-type node index (so a query visits only nodes of
 * its own kind, never the whole tree), a match is a {@see NodeMatch}, and decorators extend it.
 *
 * A selector declares the node CLASSES it can match (a method call is a `MethodCall` or a
 * `NullsafeMethodCall`); `where`/`reject` see a {@see NodeMatch}.
 */
final class Query extends BaseQuery
{
    /**
     * @param  \Closure(Node): bool  $selector
     * @param  list<class-string<Node>>|null  $types  exact node classes the selector can match; null = any
     */
    public function __construct(
        private readonly Codebase $codebase,
        private readonly \Closure $selector,
        private readonly ?array $types = null,
    ) {}

    /**
     * Keep calls whose receiver resolves to any of $classes (a class or a base it extends) — and
     * drop calls made from INSIDE one of them (a request reading its own input is fine; the smell
     * is outside code reaching in).
     */
    public function isUsedOn(string ...$classes): self
    {
        $targets = array_map(static fn (string $class): string => ltrim($class, '\\'), $classes);
        $codebase = $this->codebase;

        return $this->filter(static function (AstNode $node) use ($targets, $codebase): bool {
            $enclosing = $node->enclosingClassName();
            $receiver = ReceiverResolver::typeOf($node);

            foreach ($targets as $target) {
                if ($enclosing !== null && self::isA($codebase, $enclosing, $target)) {
                    return false;
                }

                if ($receiver !== null && self::isA($codebase, $receiver, $target)) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * Keep matches whose enclosing class is (a subclass of) $class.
     */
    public function withinClass(string $class): self
    {
        $target = ltrim($class, '\\');
        $codebase = $this->codebase;

        return $this->filter(static fn (AstNode $node): bool =>
            ($enclosing = $node->enclosingClassName()) !== null && self::isA($codebase, $enclosing, $target));
    }

    /**
     * Keep matches NOT inside $class.
     */
    public function notWithinClass(string $class): self
    {
        $target = ltrim($class, '\\');
        $codebase = $this->codebase;

        return $this->filter(static fn (AstNode $node): bool =>
            ($enclosing = $node->enclosingClassName()) === null || ! self::isA($codebase, $enclosing, $target));
    }

    /**
     * Keep matches with a call to $name within $lines (same file).
     */
    public function inProximityOf(string $name, int $lines = 5): self
    {
        return $this->filter(static fn (AstNode $node): bool => $node instanceof NodeMatch && $node->near($name, $lines));
    }

    protected function selected(): iterable
    {
        foreach ($this->codebase->nodes($this->types) as [$node, $file]) {
            if (($this->selector)($node)) {
                yield [$node, $file];
            }
        }
    }

    protected function wrap(mixed $candidate, ?string $as): object
    {
        [$node, $file] = $candidate;

        return $this->codebase->wrap($node, $file, $as);
    }

    protected function matchClass(): string
    {
        return NodeMatch::class;
    }

    private static function isA(Codebase $codebase, string $class, string $target): bool
    {
        if ($class === $target) {
            return true;
        }

        // Autoloadable code: resolve via reflection (also catches `implements`). A parsed-only
        // codebase (the fixture, or any tree we don't load) falls back to the class graph the
        // engine already built — a subclass declared in the codebase still resolves.
        if (class_exists($class) || interface_exists($class)) {
            return is_a($class, $target, true);
        }

        return $codebase->extends($class, $target);
    }
}
