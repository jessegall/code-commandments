<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Laravel;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Support\ConfigKeys;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\DuplicatedConfigDefault;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A read of a config key that supplies its OWN fallback while the config file already states one —
 * `intOr('shop.realtime.port', 8086)` against `'port' => env('PORT', 8086)`. The same decision lives
 * in two places and the second is invisible from the first, so editing either leaves the other
 * quietly wrong. Only keys whose file the project OWNS are considered ({@see ConfigKeys}), and only
 * where the file genuinely states a default — a bare `env('VAR')` states none, so a reader's fallback
 * is then the only truth and nothing is duplicated. Points at laravel-idioms.
 */
final class DuplicatedConfigDefaultDetector implements Detector
{
    public function sin(): Sin
    {
        return new DuplicatedConfigDefault();
    }

    public function find(Codebase $codebase): array
    {
        $keys = ConfigKeys::forCodebase($codebase);

        return $codebase
            ->where(static fn (AstNode $node): bool => $node->stringArgument() !== null)
            ->where(static fn (AstNode $node): bool => $node->argumentCount() >= 2)
            ->reject(static fn (AstNode $node): bool => $node->resultIsDiscarded())
            ->where(static fn (AstNode $node): bool => $keys->declaresDefault($node->stringArgument() ?? ''))
            ->get();
    }

}
