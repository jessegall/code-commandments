<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\GenericException;
use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;

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

    public function sin(): Sin
    {
        return new GenericException();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->where(static fn (AstNode $node): bool => in_array($node->newClassName(), self::GENERIC, true))
            ->where(static fn (AstNode $node): bool => $node->parent()->isThrow())
            ->get();
    }
}
