<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Results;

/**
 * The stake a prophet's findings carry — the user's to set, not the package's to
 * dictate. A SIN blocks the commit; an ADMONITION advises (a prophet's gentle
 * caution — heed it, but you are not condemned); OFF silences the prophet entirely
 * (it stays listed + discoverable, just never reports). Set fluently
 * (`Prophet::make()->severity(Severity::Sin)` / `->disabled()`) or via the legacy
 * `['severity' => 'sin']` config; resolution layers prophet → doctrine/band → the
 * prophet's own natural default.
 */
enum Severity: string
{
    case Sin = 'sin';
    case Admonition = 'admonition';
    case Off = 'off';

    /**
     * Parse a config string (case-insensitive). 'warning' stays an alias for
     * Admonition so the legacy `['severity' => 'warning']` configs keep working;
     * 'disabled'/'ignore' alias Off.
     */
    public static function fromName(string $name): ?self
    {
        return match (strtolower(trim($name))) {
            'sin', 'error', 'block' => self::Sin,
            'admonition', 'admonish', 'warning', 'warn', 'advisory', 'advise', 'guidance', 'counsel' => self::Admonition,
            'off', 'disabled', 'ignore', 'silent' => self::Off,
            default => null,
        };
    }
}
