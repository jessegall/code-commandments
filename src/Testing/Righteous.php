<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

use Attribute;

/**
 * Marks a fixture declaration as the good-code counterpart of a sin — the "good" half of a
 * skill's bad → good example, read off the AST by {@see RighteousMarkers}. Repeatable; `$detector`
 * (class or slug) matches {@see Sinful}. Any unmarked clean code is righteous too.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Righteous
{
    public function __construct(
        public readonly string $detector,
        public readonly ?int $line = null,
    ) {}
}
