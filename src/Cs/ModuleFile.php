<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cs;

use JesseGall\CodeCommandments\CommentRuns;
use JesseGall\CodeCommandments\Language;
use JesseGall\CodeCommandments\NodeSpans;
use JesseGall\CodeCommandments\ParsedModule;
use JesseGall\CodeCommandments\Span;
use JesseGall\PhpTypes\Option;

/**
 * One C# file as the Roslyn bridge read it: its source, its tree, and the way up from any node.
 */
final class ModuleFile implements ParsedModule
{
    use CommentRuns;
    use NodeSpans;

    /**
     * @var list<Node>|null
     */
    private ?array $nodes = null;

    /**
     * @var array<int, Node>|null  each node's parent, by the child's object id
     */
    private ?array $parents = null;

    /**
     * @param  list<Comment>  $comments
     */
    private function __construct(
        public readonly string $file,
        public readonly string $source,
        public readonly Node $root,
        public readonly int $errors,
        private readonly bool $test,
        private readonly array $comments,
    ) {}

    /**
     * $written, named $file — the path as the walk found it, which the bridge wrote resolved.
     */
    public static function fromBridge(WrittenFile $written, string $file): self
    {
        return new self($file, (string) file_get_contents($file), $written->root, $written->errors, $written->test, $written->comments);
    }

    /**
     * Every comment in the file, in the order it is written.
     *
     * @return list<Comment>
     */
    public function comments(): array
    {
        return $this->comments;
    }

    /**
     * Every node in the file that is not an expression, parents before their children.
     *
     * @return list<Node>
     */
    public function nodes(): array
    {
        return $this->nodes ??= $this->root->descendants();
    }

    /**
     * Every expression in the file, each once — the file's outermost expressions, and everything each one
     * holds, however many nodes sit between.
     *
     * @return list<Node>
     */
    public function expressions(): array
    {
        return array_merge([], ...array_map(static fn (Node $outermost): array => $outermost->flatten(), $this->root->outermostExpressions()));
    }

    /**
     * Is $value compared as a case where it stands — a side of `==` or `!=`, a `case` label, or a
     * constant pattern (`Status.Paid => …`, `is Status.Paid`)?
     */
    public function isComparedAsACase(Node $value): bool
    {
        return $this->parentOf($value)->isSomeAnd(fn (Node $parent): bool => $parent->is('ParenthesizedExpression')
            ? $this->isComparedAsACase($parent)
            : $parent->is('EqualsExpression', 'NotEqualsExpression', 'CaseSwitchLabel', 'ConstantPattern'));
    }

    /**
     * The node $node sits directly inside — none for the file's root.
     *
     * @return Option<Node>
     */
    public function parentOf(Node $node): Option
    {
        $this->parents ??= self::parentIds($this->root);

        return Option::fromNullable($this->parents[spl_object_id($node)] ?? null);
    }

    /**
     * The nodes $node sits inside, innermost first, out to the file's root.
     *
     * @return list<Node>
     */
    public function ancestorsOf(Node $node): array
    {
        $this->parents ??= self::parentIds($this->root);
        $ancestors = [];

        while (isset($this->parents[spl_object_id($node)])) {
            $node = $this->parents[spl_object_id($node)];
            $ancestors[] = $node;
        }

        return $ancestors;
    }

    /**
     * Does this file belong to a test project — code that exercises the product rather than being it?
     */
    public function isTest(): bool
    {
        return $this->test;
    }

    public function language(): Language
    {
        return Language::CSharp;
    }

    public function lineAt(int $offset): int
    {
        return Span::lineAt($this->source, $offset);
    }

    public function spanAt(int $start, int $end): Span
    {
        return new Span($this->file, $this->source, $start, $end);
    }

    /**
     * @return array<int, Node>
     */
    private static function parentIds(Node $parent): array
    {
        $parents = [];

        foreach ($parent->children as $child) {
            $parents[spl_object_id($child)] = $parent;
            $parents += self::parentIds($child);
        }

        return $parents;
    }
}
