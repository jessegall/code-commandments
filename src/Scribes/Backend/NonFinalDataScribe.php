<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Scribes\Draft;
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
 */
final class NonFinalDataScribe extends RepentScribe
{
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
            if ($this->isPromotedMutable($param)) {
                $writer->insertAt(($param->type ?? $param->var)->getStartFilePos(), 'readonly ');
            }
        }
    }

    private function isPromotedMutable(Param $param): bool
    {
        return $param->flags !== 0 && ($param->flags & Modifiers::READONLY) === 0;
    }
}
