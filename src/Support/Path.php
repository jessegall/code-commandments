<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

/**
 * Small path helpers shared by the CLI commands that print file locations. One home so `hints` and
 * `repent` don't each keep a private copy of "strip the project root off an absolute path".
 */
final class Path
{
    /**
     * $path relative to $base when it lives under it (`{base}/foo` → `foo`), else $path unchanged.
     */
    public static function relative(string $path, string $base): string
    {
        return str_starts_with($path, $base . '/') ? substr($path, strlen($base) + 1) : $path;
    }
}
