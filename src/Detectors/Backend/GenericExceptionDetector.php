<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Detector;
use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Name;

/**
 * Throwing a generic SPL/base exception (`throw new \RuntimeException(...)`)
 * instead of a named domain exception. Points at the exceptions skill.
 */
final class GenericExceptionDetector implements Detector
{
    private const array GENERIC = [
        'Exception',
        'Error',
        'RuntimeException',
        'LogicException',
        'InvalidArgumentException',
        'DomainException',
        'UnexpectedValueException',
        'OutOfRangeException',
        'OutOfBoundsException',
        'RangeException',
        'LengthException',
    ];

    public function skill(): string
    {
        return 'exceptions';
    }

    public function find(Codebase $codebase): array
    {
        return $codebase->where(static function (Node $node): bool {
            if (! $node instanceof New_ || ! $node->class instanceof Name) {
                return false;
            }

            if (! in_array($node->class->toString(), self::GENERIC, true)) {
                return false;
            }

            return $node->getAttribute('parent') instanceof Throw_;
        })->get();
    }
}
