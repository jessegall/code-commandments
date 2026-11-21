<?php

declare(strict_types=1);

namespace CodeCommandments\PhpBridge;

/**
 * One node of the version 5 tree before it is numbered: its kind, byte span, facts, owned parts and links.
 */
final class TreeNode
{
    public int $id = -1;

    /**
     * @param  array<string, mixed>  $facts
     * @param  array<string, TreeNode|list<TreeNode>>  $parts
     * @param  array<string, TreeNode|list<TreeNode>>  $links
     */
    public function __construct(
        public string $kind,
        public int $start,
        public int $end,
        public array $facts = [],
        public array $parts = [],
        public array $links = [],
    ) {}

    /**
     * The node with one fact set whatever its value, for a fact whose false or empty value is still said.
     */
    public function with(string $fact, mixed $value): self
    {
        $this->facts[$fact] = $value;

        return $this;
    }

    /**
     * Every node it owns, in source order.
     *
     * @return list<TreeNode>
     */
    public function children(): array
    {
        $held = [];
        foreach ($this->parts as $part) {
            foreach (is_array($part) ? $part : [$part] as $child) {
                $held[] = $child;
            }
        }
        usort($held, static fn (TreeNode $a, TreeNode $b): int => [$a->start, -$a->end] <=> [$b->start, -$b->end]);

        return $held;
    }

    /**
     * Numbers the tree in pre-order, children in source order, and lists its nodes by number.
     *
     * @return list<TreeNode>
     */
    public function numbered(): array
    {
        $order = [];
        $pending = [$this];
        while ($pending !== []) {
            $node = array_pop($pending);
            $node->id = count($order);
            $order[] = $node;
            foreach (array_reverse($node->children()) as $child) {
                $pending[] = $child;
            }
        }

        return $order;
    }

    /**
     * The node as the stream writes it; `line` gives the line an offset is on.
     *
     * @param  callable(int): int  $line
     * @return array<string, mixed>
     */
    public function written(callable $line): array
    {
        $out = ['id' => $this->id, 'kind' => $this->kind, 'span' => [$this->start, $this->end, $line($this->start)]] + $this->facts;
        if ($this->parts !== []) {
            $out['parts'] = array_map(
                static fn (TreeNode|array $part): array => is_array($part)
                    ? array_map(static fn (TreeNode $child): array => $child->written($line), $part)
                    : $part->written($line),
                $this->parts,
            );
        }
        if ($this->links !== []) {
            $out['links'] = array_map(
                static fn (TreeNode|array $target): int|array => is_array($target)
                    ? array_map(static fn (TreeNode $each): int => $each->id, $target)
                    : $target->id,
                $this->links,
            );
        }

        return $out;
    }
}
