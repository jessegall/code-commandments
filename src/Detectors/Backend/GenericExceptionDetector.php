<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Support\Nodes;
use JesseGall\CodeCommandments\Detectors\Detector;
use PhpParser\Node;

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
        return $codebase->where(static fn (Node $node): bool =>
            in_array(Nodes::newClassName($node), self::GENERIC, true)
            && Nodes::isThrow(Nodes::parentOf($node)))->get();
    }
}
