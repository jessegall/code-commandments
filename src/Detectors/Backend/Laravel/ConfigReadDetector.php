<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Laravel;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\ConfigRead;
use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;

/**
 * Reading configuration with `config(...)` inside a class instead of injecting a
 * typed config object. Points at laravel-idioms.
 */
final class ConfigReadDetector implements Detector
{
    public function sin(): Sin
    {
        return new ConfigRead();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereFunction('config')
            ->where(static fn (AstNode $node): bool => $node->isEnclosedInClass())
            ->get();
    }
}
