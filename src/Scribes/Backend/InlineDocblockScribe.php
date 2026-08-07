<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes\Backend;

use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Ast\Support\Docblock;
use JesseGall\CodeCommandments\Scribes\RepentScribe;
use JesseGall\CodeCommandments\Span;
use JesseGall\CodeCommandments\Scribes\Writer;

/**
 * Fixes {@see \JesseGall\CodeCommandments\Detectors\Backend\InlineDocblockDetector}: the docblock is
 * re-emitted as a block — delimiters on their own lines, content verbatim, at the declaration's own
 * indentation. Text is never touched, only where the lines break.
 */
final class InlineDocblockScribe extends RepentScribe
{
    public function rewrite(array $findings): array
    {
        $draft = $this->draft([]);

        foreach ($findings as $finding) {
            if ($finding instanceof NodeMatch && $finding->node?->getDocComment() !== null) {
                $this->expand($draft, $finding);
            }
        }

        return $draft->rewrites();
    }

    private function expand(\JesseGall\CodeCommandments\Scribes\Draft $draft, NodeMatch $finding): void
    {
        $doc = $finding->node->getDocComment();
        $indent = Span::ownLineIndent($finding->file->source, $doc->getStartFilePos()) ?? '';

        Writer::for($draft, $finding)->replaceDocblock($finding->node, Docblock::canonical($doc->getText(), $indent));
    }
}
