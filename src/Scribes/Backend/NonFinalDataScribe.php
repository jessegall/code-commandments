<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Scribes\Draft;
use JesseGall\CodeCommandments\Scribes\NeedsCodebase;
use JesseGall\CodeCommandments\Scribes\RepentScribe;
use JesseGall\CodeCommandments\Span;
use JesseGall\CodeCommandments\Scribes\Writer;
use PhpParser\Modifiers;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;

/**
 * Fixes {@see \JesseGall\CodeCommandments\Detectors\Backend\Spatie\NonFinalDataDetector}: a Spatie
 * `Data` class is a leaf, so seal it `final` and make every promoted property `readonly` —
 * the immutable-DTO shape the spatie-data skill teaches. Two insertions, no reflow: `final `
 * before `class`, and `readonly ` on each promoted ctor param still missing it.
 *
 * A property the BASE declares mutable keeps its word, because PHP will not let a subclass seal what
 * an ancestor left open and the base is exempt from the sin precisely for being extended (#482) — so
 * such a class is sealed `final` and no further.
 */
final class NonFinalDataScribe extends RepentScribe implements NeedsCodebase
{
    private ?Codebase $codebase = null;

    public function withCodebase(Codebase $codebase): void
    {
        $this->codebase = $codebase;
    }

    public function rewrite(array $findings): array
    {
        $draft = $this->draft([]);

        foreach ($this->classDeclarations($findings) as $match) {
            $this->seal($draft, $match, $match->node);
        }

        return $draft->rewrites();
    }

    private function seal(Draft $draft, NodeMatch $match, Class_ $class): void
    {
        $writer = Writer::for($draft, $match);

        // `final ` immediately before the `class` keyword (after any attributes/docblock).
        if ($class->name !== null) {
            $keyword = Span::before($match->file->source, $class->name->getStartFilePos(), 'class');

            if ($keyword !== null) {
                $writer->insertAt($keyword, 'final ');
            }
        }

        // `readonly ` before the type (or the variable) of each promoted, non-readonly param.
        foreach (AstNode::constructorParamsOf($class) as $param) {
            if ($this->isSealable($param, $match)) {
                $writer->insertAt(($param->type ?? $param->var)->getStartFilePos(), 'readonly ');
            }
        }
    }

    /**
     * Is this a promoted property the class may seal — promoted, not already readonly, and not one an
     * ancestor declares mutable, which PHP forbids sealing.
     */
    private function isSealable(Param $param, NodeMatch $match): bool
    {
        if ($param->flags === 0 || ($param->flags & Modifiers::READONLY) !== 0) {
            return false;
        }

        $name = AstNode::variableNameOf($param->var);

        return $name !== null && ! $this->codebase?->inheritsMutableProperty($match->enclosingClassName(), $name);
    }
}
