<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Detector;

/**
 * Reading configuration with `config(...)` inside a class instead of injecting a
 * typed config object. Points at laravel-idioms.
 */
final class ConfigReadDetector implements Detector
{
    public function skill(): string
    {
        return 'laravel-idioms';
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereFunction('config')
            ->where(static fn (AstNode $node): bool => $node->enclosingClassName() !== null)
            ->get();
    }
}
