<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes\Backend;

use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Ast\Support\Docblock;
use JesseGall\CodeCommandments\Scribes\Draft;
use JesseGall\CodeCommandments\Scribes\RepentScribe;
use JesseGall\CodeCommandments\Scribes\Span;
use JesseGall\CodeCommandments\Scribes\Writer;
use PhpParser\Comment;

/**
 * Fixes {@see \JesseGall\CodeCommandments\Detectors\Backend\StackedDocblockDetector}: the stack becomes
 * ONE canonical block, each original's content kept verbatim and in order, separated by a blank line so
 * the paragraphs stay distinct. Nothing is dropped — the invisible blocks become visible.
 */
final class StackedDocblockScribe extends RepentScribe
{
    public function rewrite(array $findings): array
    {
        $draft = $this->draft([]);

        foreach ($findings as $finding) {
            if ($finding instanceof NodeMatch) {
                $this->merge($draft, $finding);
            }
        }

        return $draft->rewrites();
    }

    private function merge(Draft $draft, NodeMatch $finding): void
    {
        $blocks = $finding->docblocks();

        if (count($blocks) < 2) {
            return;
        }

        $indent = Span::ownLineIndent($finding->file->source, $blocks[0]->getStartFilePos()) ?? '';
        $texts = array_map(static fn (Comment $c): string => $c->getText(), $blocks);

        Writer::for($draft, $finding)->replaceComments($blocks, Docblock::merge($texts, $indent));
    }
}
