<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

use Attribute;

/**
 * Marks a fixture declaration as a deliberate LOOK-ALIKE a detector must not flag — usually a
 * documented exemption — read off the AST by {@see SinMarkers}. It is what the docs publish as the
 * "good" half only where no {@see Fixed} resolution exists, since dodging a rule legitimately is a
 * different thing from obeying it. Repeatable; `$detector` (class or slug) matches {@see Sinful}.
 * Any unmarked clean code is righteous too.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION | Attribute::IS_REPEATABLE)]
final class Righteous
{
    public function __construct(
        public readonly string $detector,
        public readonly ?int $line = null,
    ) {}
}
