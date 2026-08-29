<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ts;

use JesseGall\CodeCommandments\Span;
use JesseGall\CodeCommandments\Ts\Expr\Expr;
use JesseGall\CodeCommandments\Ts\Node\ClassDecl;
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
     * @var array<string, ?string>|null
     */
    private ?array $bindingTypes = null;

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
     * Every EXPRESSION the module holds, each sub-expression included and each paired with the class
     * it sits inside — so a selector reaches a call nested in the argument of another call, and a
     * rule about `this.field` can ask what that field was declared as.
     *
     * @return list<array{0: Expr, 1: ?ClassDecl}>
     */
    public function expressions(): array
    {
        $expressions = [];
        $this->gather($this->module->children(), null, $expressions);

        return $expressions;
    }

    /**
     * Every expression under $nodes, paired with the CLASS it sits inside — carried down as the walk
     * descends, because a node holds no pointer to its parent and a rule about `this.field` has to
     * know whose field it is.
     *
     * @param  list<Node>  $nodes
     * @param  list<array{0: Expr, 1: ?ClassDecl}>  $found
     */
    private function gather(array $nodes, ?ClassDecl $enclosing, array &$found): void
    {
        foreach ($nodes as $node) {
            $within = $node instanceof ClassDecl ? $node : $enclosing;

            foreach ($node->expressions() as $expression) {
                foreach ($expression->flatten() as $part) {
                    $found[] = [$part, $within];
                }
            }

            $this->gather($node->children(), $within, $found);
        }
    }

    /**
     * The type $binding was DECLARED with here — a function parameter's annotation, a typed
     * `const`/`let` — reduced to the single named type it refers to. Null when the module never
     * says, when it says two different things, or when the annotation names no one type: a rule
     * pairing this module with another language must not guess whose value it is holding.
     */
    public function typeOfBinding(string $binding): ?string
    {
        return $this->bindingTypes()[$binding] ?? null;
    }

    /**
     * Every binding this module declares a type for. A name declared twice with DIFFERENT types is
     * recorded as unknown rather than as the last one seen.
     *
     * @return array<string, ?string>
     */
    private function bindingTypes(): array
    {
        if ($this->bindingTypes !== null) {
            return $this->bindingTypes;
        }

        $types = [];

        foreach ($this->nodes() as $node) {
            foreach (self::annotations($node) as $name => $type) {
                $types[$name] = array_key_exists($name, $types) && $types[$name] !== $type ? null : $type;
            }
        }

        return $this->bindingTypes = $types;
    }

    /**
     * What $node declares, as `name => the one type it names` — nothing for a node that binds no
     * name, or annotates it with a type naming none or several.
     *
     * @return array<string, string>
     */
    private static function annotations(Node $node): array
    {
        $named = $node->annotation()?->references() ?? [];

        if (count($named) !== 1) {
            return [];
        }

        return array_fill_keys($node->declaredNames(), $named[0]);
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
