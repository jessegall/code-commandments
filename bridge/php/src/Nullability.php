<?php

declare(strict_types=1);

namespace CodeCommandments\PhpBridge;

/**
 * Whether a written type admits null, and how the source says so: not at all, by what it is (`null`, `mixed`, a union
 * holding one), or marked `?T` — which the contract writes after the rest of the type, as the marker is read last.
 */
enum Nullability
{
    case None;
    case Admitted;
    case Marked;

    public function admitsNull(): bool
    {
        return $this !== self::None;
    }
}
