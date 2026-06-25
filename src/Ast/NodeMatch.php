<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast;

use JesseGall\CodeCommandments\Ast\Support\Calls;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\NodeFinder;

/**
 * A matched node with its location and surrounding context — the granular
 * handle a detector inspects (its args, receiver, enclosing class/method, and
 * what sits near it) rather than a bare `file:line` string.
 */
final class NodeMatch
{
    public function __construct(
        public readonly Node $node,
        public readonly ParsedFile $file,
    ) {}

    public function line(): int
    {
        return $this->node->getStartLine();
    }

    public function location(): string
    {
        return "{$this->file->path}:{$this->line()}";
    }

    /**
     * The called name when this match is a call node.
     */
    public function callName(): ?string
    {
        return Calls::name($this->node);
    }

    /**
     * @return list<Arg>
     */
    public function arguments(): array
    {
        $args = $this->node->args ?? [];

        return array_values(array_filter($args, static fn ($arg): bool => $arg instanceof Arg));
    }

    public function enclosingClass(): ?ClassLike
    {
        return $this->walkUp(static fn (Node $node): bool => $node instanceof ClassLike);
    }

    public function enclosingClassName(): ?string
    {
        $class = $this->enclosingClass();

        if ($class === null) {
            return null;
        }

        return ($class->namespacedName ?? null)?->toString() ?? $class->name?->toString();
    }

    public function enclosingFunction(): ?FunctionLike
    {
        return $this->walkUp(static fn (Node $node): bool => $node instanceof FunctionLike);
    }

    /**
     * Is there a call to $name within $lines of this match, in the same file?
     */
    public function near(string $name, int $lines = 5): bool
    {
        $line = $this->line();

        foreach ((new NodeFinder)->find($this->file->ast, static fn (Node $n): bool => Calls::name($n) === $name) as $other) {
            if ($other !== $this->node && abs($other->getStartLine() - $line) <= $lines) {
                return true;
            }
        }

        return false;
    }

    private function walkUp(callable $test): ?Node
    {
        $node = $this->node->getAttribute('parent');

        while ($node instanceof Node) {
            if ($test($node)) {
                return $node;
            }

            $node = $node->getAttribute('parent');
        }

        return null;
    }
}
