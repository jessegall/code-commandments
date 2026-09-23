<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py;

use JesseGall\CodeCommandments\Language;
use JesseGall\CodeCommandments\ParsedModule;
use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\Node\ClassDef;
use JesseGall\CodeCommandments\Py\Node\FunctionDef;
use JesseGall\CodeCommandments\Py\Node\Module;
use JesseGall\CodeCommandments\Py\Node\Node;
use JesseGall\CodeCommandments\Span;

/**
 * One parsed Python file: its module tree, its path and source — so a node can say which line it is on
 * — and the flat views a selector filters: every node, every expression, which functions are methods.
 */
final class ModuleFile implements ParsedModule
{
    /**
     * @var list<Node>|null
     */
    private ?array $nodes = null;

    /**
     * @var array<int, true>|null  the object ids of every `def` written directly in a class body
     */
    private ?array $methods = null;

    private function __construct(
        public readonly Module $module,
        public readonly string $file,
        public readonly string $source,
    ) {}

    public static function fromFile(string $source, string $file): self
    {
        return new self(Parser::module($source), $file, $source);
    }

    /**
     * Every node in the module, parents before their children, walked once.
     *
     * @return list<Node>
     */
    public function nodes(): array
    {
        return $this->nodes ??= $this->module->descendants();
    }

    /**
     * Every expression the module holds, each sub-expression included.
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

    /**
     * Is $function written directly in a class body — a method, rather than a module or nested function?
     */
    public function isMethod(FunctionDef $function): bool
    {
        $this->methods ??= $this->methodIds();

        return isset($this->methods[spl_object_id($function)]);
    }

    public function language(): Language
    {
        return Language::Python;
    }

    public function nodeSpans(): array
    {
        return array_map(static fn (Node $node) => [$node->start, $node->end], $this->nodes());
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
     * @return array<int, true>
     */
    private function methodIds(): array
    {
        $ids = [];

        foreach ($this->nodes() as $node) {
            if (! $node instanceof ClassDef) {
                continue;
            }

            foreach ($node->body->body as $member) {
                if ($member instanceof FunctionDef) {
                    $ids[spl_object_id($member)] = true;
                }
            }
        }

        return $ids;
    }
}
