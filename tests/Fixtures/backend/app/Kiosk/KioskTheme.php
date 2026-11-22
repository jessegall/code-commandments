<?php

namespace Shop\Kiosk;

use JesseGall\CodeCommandments\Sins\Backend\MemberOutOfOrder;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Support\Concerns\HasUuid;

#[Sinful(MemberOutOfOrder::class)]
final class KioskTheme
{
    public const ACCENT = '#0a7d3b';

    public const BACKDROP = '#f4f4f0';

    use HasUuid;

    public function __construct(private array $attributes = []) {}

    public function contrastWith(string $colour): string
    {
        return $colour === self::BACKDROP ? self::ACCENT : self::BACKDROP;
    }
}
