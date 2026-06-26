<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast;

use JesseGall\CodeCommandments\Ast\Support\ReceiverResolver;
use PhpParser\Node;
use PhpParser\NodeFinder;

/**
 * A fluent pattern over the codebase: a node selector plus chained checks. Each
 * `where`/`reject` is one check over a fluent {@see AstNode}; they AND together.
 * Terminals run the pattern and return rich {@see NodeMatch} results.
 */
final class Query
{
    /**
     * @var list<\Closure(AstNode): bool>
     */
    private array $filters = [];

    /**
     * @param  \Closure(Node): bool  $selector
     */
    public function __construct(
        private readonly Codebase $codebase,
        private readonly \Closure $selector,
    ) {}

    /**
     * Keep matches passing the check.
     *
     * @param  \Closure(AstNode): bool  $check
     */
    public function where(\Closure $check): self
    {
        $this->filters[] = $check;

        return $this;
    }

    /**
     * Keep matches that do NOT pass the check (the inverse of {@see where}).
     *
     * @param  \Closure(AstNode): bool  $check
     */
    public function reject(\Closure $check): self
    {
        $this->filters[] = static fn (AstNode $node): bool => ! $check($node);

        return $this;
    }

    /**
     * Keep calls whose receiver resolves to any of $classes (a class or a base it
     * extends) — and drop calls made from INSIDE one of them (a request reading its
     * own input is fine; the smell is outside code reaching in).
     */
    public function isUsedOn(string ...$classes): self
    {
        $targets = array_map(static fn (string $class): string => ltrim($class, '\\'), $classes);

        return $this->where(static function (AstNode $node) use ($targets): bool {
            $enclosing = $node->enclosingClassName();
            $receiver = ReceiverResolver::typeOf($node);

            foreach ($targets as $target) {
                if ($enclosing !== null && self::isA($enclosing, $target)) {
                    return false;
                }

                if ($receiver !== null && self::isA($receiver, $target)) {
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

        return $this->where(static fn (AstNode $node): bool =>
            ($enclosing = $node->enclosingClassName()) !== null && self::isA($enclosing, $target));
    }

    /**
     * Keep matches NOT inside $class.
     */
    public function notWithinClass(string $class): self
    {
        $target = ltrim($class, '\\');

        return $this->where(static fn (AstNode $node): bool =>
            ($enclosing = $node->enclosingClassName()) === null || ! self::isA($enclosing, $target));
    }

    /**
     * Keep matches with a call to $name within $lines (same file).
     */
    public function inProximityOf(string $name, int $lines = 5): self
    {
        $this->filters[] = static fn (AstNode $node): bool => $node instanceof NodeMatch && $node->near($name, $lines);

        return $this;
    }

    /**
     * @return list<NodeMatch>
     */
    public function get(): array
    {
        $finder = new NodeFinder;
        $matches = [];

        foreach ($this->codebase->files() as $file) {
            foreach ($finder->find($file->ast, $this->selector) as $node) {
                $match = new NodeMatch($node, $file);

                foreach ($this->filters as $filter) {
                    if (! $filter($match)) {
                        continue 2;
                    }
                }

                $matches[] = $match;
            }
        }

        return $matches;
    }

    /**
     * @return list<string>
     */
    public function locations(): array
    {
        return array_map(static fn (NodeMatch $match): string => $match->location(), $this->get());
    }

    public function count(): int
    {
        return count($this->get());
    }

    public function first(): ?NodeMatch
    {
        return $this->get()[0] ?? null;
    }

    private static function isA(string $class, string $target): bool
    {
        if ($class === $target) {
            return true;
        }

        if (class_exists($class) || interface_exists($class)) {
            return is_a($class, $target, true);
        }

        return false;
    }
}
