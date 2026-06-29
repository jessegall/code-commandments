<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

/**
 * The outcome of resolving the {@see Canon}: the absolute source roots to scan,
 * whether the canon file was just hydrated (so the caller can announce it once),
 * and where that file lives.
 */
final class CanonResolution
{
    /**
     * @param  list<string>  $paths  absolute source roots to scan
     */
    public function __construct(
        public readonly array $paths,
        public readonly bool $hydrated,
        public readonly string $file,
    ) {}
}
