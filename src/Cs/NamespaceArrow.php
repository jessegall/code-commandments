<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cs;

/**
 * One reference from code in namespace $from to a type the codebase declares in namespace $to, named where
 * it is written.
 */
final readonly class NamespaceArrow
{
    public function __construct(
        public NodeMatch $at,
        public string $from,
        public string $to,
    ) {}
}
