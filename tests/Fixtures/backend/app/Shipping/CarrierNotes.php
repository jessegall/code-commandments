<?php

namespace Shop\Shipping;

use JesseGall\CodeCommandments\Sins\Backend\NullableRegistryLookup;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * The notes a carrier left on its last pickup, kept by carrier code; every reader asks whether a note is there.
 */
final class CarrierNotes
{
    /**
     * @param  array<string, array{text: string, url?: string}>  $notes
     */
    public function __construct(private array $notes) {}

    /**
     * @return array{text: string, url?: string}|null
     */
    #[Righteous(NullableRegistryLookup::class)]
    public function noteFor(string $carrier): ?array
    {
        return $this->notes[$carrier] ?? null;
    }

    public function hasNote(string $carrier, bool $asked): bool
    {
        $note = $asked ? $this->noteFor($carrier) : null;

        return ! empty($note);
    }

    public function linkFor(string $carrier): string
    {
        $note = $this->noteFor($carrier);

        return isset($note['url']) ? $note['url'] : '';
    }
}
