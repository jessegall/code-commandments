<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Sins\Backend\SideTableTrait;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Which zones are refrigerated is a constant naming them, read back through a match arm. A zone
 * added tomorrow is quietly ambient, and the enum gives it nowhere to say otherwise.
 */
#[Sinful(SideTableTrait::class)]
enum ShelfZone: string
{
    case Ambient = 'ambient';
    case Chilled = 'chilled';
    case Frozen = 'frozen';
    case Bakery = 'bakery';
    case Cellar = 'cellar';

    private const REFRIGERATED = [self::Chilled, self::Frozen];

    public function holdsAt(): int
    {
        return match (in_array($this, self::REFRIGERATED, true)) {
            true => 4,
            false => 20,
        };
    }

    public function signWriting(): string
    {
        return ucfirst($this->value) . ' — ' . $this->holdsAt() . '°C';
    }

    public function fitsBeside(self $other): bool
    {
        return abs($this->holdsAt() - $other->holdsAt()) <= 4;
    }

    public static function walkedInOrder(): array
    {
        return array_map(static fn (self $zone): string => $zone->signWriting(), self::cases());
    }
}
