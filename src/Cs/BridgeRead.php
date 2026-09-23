<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cs;

/**
 * What one request to the bridge answered: the files it wrote, and how much of them the compiler
 * resolved.
 */
final readonly class BridgeRead
{
    /**
     * @param  list<WrittenFile>  $files
     */
    public function __construct(
        public array $files,
        public Resolution $resolution,
    ) {}
}
