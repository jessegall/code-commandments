<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ts;

use JesseGall\CodeCommandments\Span;
use JesseGall\CodeCommandments\Ts\Expr\Expr;
use JesseGall\CodeCommandments\Ts\Node\Module;
use JesseGall\CodeCommandments\Ts\Node\Node;
use JesseGall\CodeCommandments\Ts\Parser;

/**
 * A parsed TypeScript module and the FILE it came from — the `.ts` twin of {@see Sfc}. The parser
 * knows only a string, so turning a node's byte range into a `file:line` is this class's job, and it
 * is where the whole-module walk that a selector draws from lives.
 */
final class ModuleFile
{
    /**
     * @var list<Node>|null
     */
    private ?array $nodes = null;

    /**
     * @param  string  $source  the WHOLE file, so a `<script>` block's node still resolves to the
     *         line it occupies in the `.vue` file around it
     */
    private function __construct(
        public readonly Module $module,
        public readonly string $file,
        public readonly string $source,
    ) {}

    /**
     * Parse a standalone `.ts` file.
     */
    public static function fromFile(string $source, string $file): self
    {
        return new self(Parser::module($source), $file, $source);
    }

    /**
     * Parse one `<script>` block of a component. $blockStart is where the block's content begins in
     * $source, so every node inside it reports a line in the SFC rather than in the block.
     */
    public static function fromBlock(string $block, string $file, string $source, int $blockStart): self
    {
        return new self(Parser::module($block, $blockStart), $file, $source);
    }

    /**
     * Every node in the module, flattened once and cached — what a selector filters rather than
     * re-walking each tree.
     *
     * @return list<Node>
     */
    public function nodes(): array
    {
        return $this->nodes ??= self::flatten($this->module->children());
    }

    /**
     * Every EXPRESSION the module's statements hold, each sub-expression included — so a selector
     * reaches a call nested in the argument of another call.
     *
     * @return list<Expr>
     */
    public function expressions(): array
    {
        $expressions = [];

        foreach ($this->nodes() as $node) {
            foreach ($node->expressions() as $expression) {
                $expressions = [...$expressions, ...$expression->flatten()];
            }
        }

        return $expressions;
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
     * @param  list<Node>  $nodes
     * @return list<Node>
     */
    private static function flatten(array $nodes): array
    {
        $flat = [];

        foreach ($nodes as $node) {
            $flat[] = $node;
            $flat = [...$flat, ...self::flatten($node->children())];
        }

        return $flat;
    }
}
