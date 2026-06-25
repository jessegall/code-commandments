<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Results;

use Throwable;

/**
 * Records a prophet that crashed while judging a file.
 */
final class ProphetFailure
{
    public function __construct(
        public readonly string $prophetClass,
        public readonly string $filePath,
        public readonly Throwable $error,
    ) {}
}
