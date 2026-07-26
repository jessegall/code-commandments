<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes\Backend;

use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Scribes\RepentScribe;
use JesseGall\CodeCommandments\Scribes\Writer;
use PhpParser\Node\Expr\ArrowFunction;

/**
 * Fixes {@see \JesseGall\CodeCommandments\Detectors\Backend\RedundantArrowReturnTypeDetector}: the
 * `: Type` comes off, and nothing else moves. The detector has already proven the expression yields
 * exactly that type, so the declaration it deletes was telling the reader something they could read
 * one token to the right.
 */
final class RedundantArrowReturnTypeScribe extends RepentScribe
{
    public function rewrite(array $findings): array
    {
        $draft = $this->draft([]);

        foreach ($findings as $finding) {
            if ($finding instanceof NodeMatch && $finding->node instanceof ArrowFunction) {
                Writer::for($draft, $finding)->removeReturnType($finding->node);
            }
        }

        return $draft->rewrites();
    }
}
