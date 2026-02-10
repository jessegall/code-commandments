<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

class DetectedProject
{
    public function __construct(
        public readonly string $name,
        public readonly string $path,
        public readonly bool $hasPhp,
        public readonly bool $hasFrontend,
        public readonly ?string $phpSourcePath,
        public readonly ?string $frontendSourcePath,
    ) {}
}
