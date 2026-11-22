<?php

namespace Shop\Shelving;

use Illuminate\Database\ConnectionInterface;
use JesseGall\CodeCommandments\Sins\Backend\ManufacturedFakeFill;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Reads the reference printed on a scanned parcel label and finds the shelf the parcel waits on.
 */
final class ParcelLabelReader
{
    public function __construct(private ConnectionInterface $database) {}

    #[Sinful(ManufacturedFakeFill::class)]
    public function scanned(string $label): int
    {
        return $this->shelfOf(strstr($label, '|', true) ?: '');
    }

    private function shelfOf(string $reference): int
    {
        return (int) $this->database->table('parcels')->where('reference', $reference)->value('shelf');
    }
}
