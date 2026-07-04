<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

use Attribute;

/**
 * Marks fixture declarations as containing sins. Repeatable. Parameters: detector
 * (skill slug or Detector::class) and optional line number. Read off AST by SinMarkers.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Sinful
{
    public function __construct(
        public readonly string $detector,
        public readonly ?int $line = null,
    ) {}
}
