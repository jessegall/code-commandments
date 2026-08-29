<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration;

/**
 * Something about the WORLD that catches anyone who does not know it — `setsid` is absent on macOS, the
 * filesystem is case-insensitive, a composition is `display: contents` so its rect is 0×0 at the page
 * corner. Impersonal, and it does not expire, so it belongs in every brief forever, exactly as written —
 * which is what separates it from a {@see Ruling}, a decision that can be overturned.
 */
final readonly class Trap
{
    public function __construct(public string $said) {}

    public function render(): string
    {
        return '  • ' . $this->said;
    }
}
