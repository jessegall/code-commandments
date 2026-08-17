<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

use JesseGall\CodeCommandments\Ast\Codebase;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Return_;

/**
 * The constant string a class ALWAYS renders through `__toString` — the value PHP substitutes wherever
 * that object meets a `string`-typed slot. Deliberately narrow: only a `__toString` whose entire body is
 * one `return '<literal>'` answers, because anything with a branch or a computation renders a value this
 * cannot know, and guessing would put words in the class's mouth.
 */
final class ScalarRendering
{
    use MemoisedPerCodebase;

    /**
     * @var array<string, string|null>
     */
    private array $rendered = [];

    public function __construct(private readonly Codebase $codebase) {}

    /**
     * The constant string $fqcn renders, or null when it renders none — it has no `__toString`, or one
     * whose result depends on something.
     */
    public function of(?string $fqcn): ?string
    {
        if ($fqcn === null) {
            return null;
        }

        return $this->rendered[$fqcn] ??= $this->resolve($fqcn);
    }

    /**
     * Does $fqcn render the empty string — an object that IS the blank, once a `string` slot coerces it?
     */
    public function isBlank(?string $fqcn): bool
    {
        return $this->of($fqcn) === '';
    }

    private function resolve(string $fqcn): ?string
    {
        $class = $this->codebase->classNamed($fqcn)->node;

        if (! $class instanceof Class_) {
            return null;
        }

        $statements = $class->getMethod('__toString')?->stmts ?? [];

        if (count($statements) !== 1 || ! $statements[0] instanceof Return_) {
            return null;
        }

        return $statements[0]->expr instanceof String_ ? $statements[0]->expr->value : null;
    }
}
