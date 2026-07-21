<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Laravel;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Support\ConfigKeys;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\DeadConfigKey;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A key declared in `config/*.php` that nothing in the codebase names. Config outlives the feature
 * that read it: the entry stays, its env var stays, and the next author adopts a setting that has not
 * been wired to anything for months. Reading a PARENT key pulls the whole subtree, so a key is alive
 * if any literal names it, contains it, or is contained by it. A config file NOTHING reads is skipped
 * entirely — it belongs to a scope this scan can't see. Points at laravel-idioms.
 */
final class DeadConfigKeyDetector implements Detector
{
    public function sin(): Sin
    {
        return new DeadConfigKey();
    }

    public function find(Codebase $codebase): array
    {
        $keys = ConfigKeys::forCodebase($codebase);

        return $codebase
            ->where(static fn (AstNode $node): bool => $keys->deadKeyAt($node->node) !== null)
            ->get();
    }
}
