<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\AssembledTemplate;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Detects a fixed multi-line template built as an array of line fragments and joined with a
 * newline. Points at backend/templates.
 */
final class AssembledTemplateDetector implements Detector
{
    /**
     * Fewer lines than this is a pair, not a template — its shape is already visible.
     */
    private const int MIN_LINES = 3;

    /**
     * At least this many of the lines must be FIXED text. A join whose parts are all computed is a
     * list being presented, and no heredoc could state it.
     */
    private const int MIN_LITERAL_LINES = 2;

    public function sin(): Sin
    {
        return new AssembledTemplate();
    }

    public function find(Codebase $codebase): array
    {
        $joins = $codebase->whereFunction('implode')
            ->where(static fn (AstNode $node): bool => $node->argument(0)->isNewlineSeparator())
            ->get();

        return array_values(array_filter($joins, static fn (NodeMatch $join): bool => self::statesATemplate($join)));
    }

    /**
     * Is what this call joins a TEMPLATE — enough lines to have a shape, and enough of them fixed
     * text for a heredoc to state it?
     */
    private static function statesATemplate(NodeMatch $join): bool
    {
        $array = $join->argumentArrayLiteral(1);

        if ($array === null || count($array->items) < self::MIN_LINES) {
            return false;
        }

        return count(AstNode::literalItemsOf($array)) >= self::MIN_LITERAL_LINES;
    }
}
