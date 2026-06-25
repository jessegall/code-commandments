<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast;

use JesseGall\CodeCommandments\Ast\Support\ReceiverResolver;
use PhpParser\Node;
use PhpParser\NodeFinder;

/**
 * A fluent pattern over the codebase: a node selector plus chained filters.
 * Filters narrow the matched nodes by context — what the call is used on, what
 * sits near it, which class it lives in. Terminals run the pattern and return
 * rich {@see NodeMatch} results (or their locations).
 */
final class Query
{
    /**
     * @var list<\Closure(NodeMatch): bool>
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
     * Keep calls whose receiver resolves to $class — and drop calls made from
     * INSIDE $class (a request reading its own input is fine; the smell is
     * outside code reaching in).
     */
    public function isUsedOn(string $class): self
    {
        $target = ltrim($class, '\\');

        $this->filters[] = static function (NodeMatch $match) use ($target): bool {
            $enclosing = $match->enclosingClassName();

            if ($enclosing !== null && self::isA($enclosing, $target)) {
                return false;
            }

            $receiver = ReceiverResolver::typeOf($match);

            return $receiver !== null && self::isA($receiver, $target);
        };

        return $this;
    }

    /**
     * Keep matches whose enclosing class is (a subclass of) $class.
     */
    public function withinClass(string $class): self
    {
        $target = ltrim($class, '\\');

        $this->filters[] = static fn (NodeMatch $match): bool =>
            ($enclosing = $match->enclosingClassName()) !== null && self::isA($enclosing, $target);

        return $this;
    }

    /**
     * Keep matches NOT inside $class.
     */
    public function notWithinClass(string $class): self
    {
        $target = ltrim($class, '\\');

        $this->filters[] = static fn (NodeMatch $match): bool =>
            ($enclosing = $match->enclosingClassName()) === null || ! self::isA($enclosing, $target);

        return $this;
    }

    /**
     * Keep matches with a call to $name within $lines (same file).
     */
    public function inProximityOf(string $name, int $lines = 5): self
    {
        $this->filters[] = static fn (NodeMatch $match): bool => $match->near($name, $lines);

        return $this;
    }

    /**
     * Granular escape hatch: keep matches passing your own predicate.
     *
     * @param  \Closure(NodeMatch): bool  $predicate
     */
    public function where(\Closure $predicate): self
    {
        $this->filters[] = $predicate;

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
