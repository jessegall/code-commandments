<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Detector;

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
        return $codebase->where(static fn (AstNode $node): bool =>
            in_array($node->newClassName(), self::GENERIC, true)
            && ($node->parent()?->isThrow() ?? false))->get();
    }
}
