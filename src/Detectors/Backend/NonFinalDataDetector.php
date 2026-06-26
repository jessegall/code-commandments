<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Detector;

/**
 * A Spatie `Data` class that is not declared `final`. A DTO is a value, not a base
 * to extend — leaving it open invites subclasses that quietly change its shape and
 * break the "the type tells the truth" contract. Seal it. Points at spatie-data.
 */
final class NonFinalDataDetector implements Detector
{
    private const string DATA = 'Spatie\\LaravelData\\Data';

    public function skill(): string
    {
        return 'spatie-data';
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereClassExtending(self::DATA)
            ->where(static fn (AstNode $node): bool => $node->isNonFinalClass())
            ->get();
    }
}
