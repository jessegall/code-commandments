<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes\Backend;

use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Scribes\Draft;
use JesseGall\CodeCommandments\Scribes\RepentScribe;
use JesseGall\CodeCommandments\Scribes\Span;
use PhpParser\Modifiers;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;

/**
 * Fixes {@see \JesseGall\CodeCommandments\Detectors\Backend\NonFinalDataDetector}: a Spatie
 * `Data` class is a leaf, so seal it `final` and make every promoted property `readonly` —
 * the immutable-DTO shape the spatie-data skill teaches. Two insertions, no reflow: `final `
 * before `class`, and `readonly ` on each promoted ctor param still missing it.
 */
final class NonFinalDataScribe extends RepentScribe
{
    public function rewrite(array $findings): array
    {
        $draft = $this->draft([]);

        foreach ($findings as $match) {
            if ($match instanceof NodeMatch && $match->node instanceof Class_) {
                $this->seal($draft, $match, $match->node);
            }
        }

        return $draft->rewrites();
    }

    private function seal(Draft $draft, NodeMatch $match, Class_ $class): void
    {
        $source = $match->file->source;
        $path = $match->file->path;

        // `final ` immediately before the `class` keyword (after any attributes/docblock).
        if ($class->name !== null) {
            $keyword = strrpos(substr($source, 0, $class->name->getStartFilePos()), 'class');

            if ($keyword !== false) {
                $draft->edit(new Span($path, $source, $keyword, $keyword), 'final ');
            }
        }

        // `readonly ` before the type (or the variable) of each promoted, non-readonly param.
        foreach ($class->getMethod('__construct')?->params ?? [] as $param) {
            if ($this->isPromotedMutable($param)) {
                $at = ($param->type ?? $param->var)->getStartFilePos();
                $draft->edit(new Span($path, $source, $at, $at), 'readonly ');
            }
        }
    }

    private function isPromotedMutable(Param $param): bool
    {
        return $param->flags !== 0 && ($param->flags & Modifiers::READONLY) === 0;
    }
}
