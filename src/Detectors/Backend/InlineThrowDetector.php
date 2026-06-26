<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Detector;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\Throw_;

/**
 * A `?? throw` buried inside a larger expression — fed into a call or
 * dereferenced on the same line instead of guarded at the top. Points at
 * guard-clauses-and-flow.
 *
 * A bare `return $x ?? throw ...;` (the throw is the whole expression) is fine;
 * the smell is `f($x ?? throw ...)` or `($x ?? throw ...)->y()`.
 */
final class InlineThrowDetector implements Detector
{
    public function skill(): string
    {
        return 'guard-clauses-and-flow';
    }

    public function find(Codebase $codebase): array
    {
        return $codebase->where(static function (Node $node): bool {
            if (! $node instanceof Coalesce || ! $node->right instanceof Throw_) {
                return false;
            }

            $parent = $node->getAttribute('parent');

            // Fed into a call argument, or dereferenced as a call receiver.
            return $parent instanceof Arg
                || (($parent instanceof MethodCall || $parent instanceof NullsafeMethodCall) && $parent->var === $node);
        })->get();
    }
}
