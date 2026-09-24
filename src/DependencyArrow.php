<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

/**
 * One reference from one part of a codebase to another — where it is written, the part it is written in, and
 * the part it reaches: a namespace, a package.
 */
final readonly class DependencyArrow
{
    public function __construct(
        public Located $at,
        public string $from,
        public string $to,
    ) {}
}
