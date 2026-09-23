<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cs;

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
    use NodeSpans;

    /**
     * @var list<Node>|null
     */
    private ?array $nodes = null;

    /**
     * @var array<int, Node>|null  each node's parent, by the child's object id
     */
    private ?array $parents = null;

    private function __construct(
        public readonly string $file,
        public readonly string $source,
        public readonly Node $root,
    ) {}

    /**
     * $written, named $file — the path as the walk found it, which the bridge wrote resolved.
     */
    public static function fromBridge(WrittenFile $written, string $file): self
    {
        return new self($file, (string) file_get_contents($file), $written->root);
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
     * Every expression in the file, each sub-expression included.
     *
     * @return list<Node>
     */
    public function expressions(): array
    {
        $expressions = [];

        foreach ([$this->root, ...$this->nodes()] as $node) {
            foreach ($node->expressions() as $expression) {
                $expressions = [...$expressions, ...$expression->flatten()];
            }
        }

        return $expressions;
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
