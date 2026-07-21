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
            ->where(static fn (AstNode $node): bool => self::readsWithFallback($node))
            ->reject(static fn (AstNode $node): bool => $node->resultIsDiscarded())
            ->where(static fn (AstNode $node): bool => $keys->declaresDefault(self::keyOf($node) ?? ''))
            ->get();
    }

    /**
     * Is this a call that names a config key by literal AND passes a second argument — the reader's
     * own fallback? The key literal is what identifies the call as a config read, whatever the helper
     * is spelled (`config`, a typed `intOr`, a facade `get`), so no helper name is hardcoded.
     *
     * A WRITE is excluded structurally rather than by name: `Config::set('k', $v);` discards its
     * result, while every read is assigned, returned or passed on. Without that, every
     * `config()->set(...)` in a test suite looked like a duplicated default.
     */
    private static function readsWithFallback(AstNode $node): bool
    {
        return count($node->arguments()) >= 2 && self::keyOf($node) !== null;
    }

    /** The dotted config key a call names in its first argument, or null. */
    private static function keyOf(AstNode $node): ?string
    {
        $first = $node->arguments()[0]->value ?? null;

        return $first instanceof \PhpParser\Node\Scalar\String_ ? $first->value : null;
    }
}
