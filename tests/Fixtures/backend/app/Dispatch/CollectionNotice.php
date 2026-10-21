<?php

namespace Shop\Dispatch;

use JesseGall\CodeCommandments\Sins\Backend\TrainWreck;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * The notice a customer gets when a parcel waits to be collected. It is built from the slips of one
 * collection round, and the null-safe arrows hide what the walk really is: the notice reads the slip,
 * then its run, then the run's depot, then the depot's address, so a postcode that moves onto the
 * depot breaks a class four objects away.
 */
final class CollectionNotice
{
    /**
     * @var list<string>
     */
    private array $lines = [];

    #[Sinful(TrainWreck::class)]
    public function collectFrom(ParcelSlip $slip): ?string
    {
        return $slip->run?->depot()?->address()?->postcode;
    }

    /**
     * @param list<ParcelSlip> $slips
     *
     * @return list<string>
     */
    public function round(array $slips): array
    {
        $this->lines = [];

        foreach ($slips as $slip) {
            $postcode = $this->collectFrom($slip);

            if ($postcode === null) {
                continue;
            }

            $this->lines[] = sprintf('%s waits at %s', $slip->barcode, $postcode);
        }

        return $this->lines;
    }
}
