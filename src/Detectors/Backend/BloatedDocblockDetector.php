<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Detector;

/**
 * A class whose docblock runs to multiple paragraphs. A one- or two-line summary
 * is documentation; an essay is a class doing too much, narrating responsibilities
 * that should be separate types. Points at documentation.
 */
final class BloatedDocblockDetector implements Detector
{
    public function skill(): string
    {
        return 'backend/documentation';
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereClass()
            ->where(static fn (AstNode $node): bool => $node->hasMultiParagraphDocblock())
            ->get();
    }
}
