<?php

namespace Shop\Shipping;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\ManualHydrationLoop;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Data\ConsignmentParcelData;

/**
 * Lists a consignment's parcels for the courier's manifest, read through the parcels relation as a property.
 */
final class ConsignmentManifest
{
    /**
     * @return list<ConsignmentParcelData>
     */
    #[Sinful(ManualHydrationLoop::class)]
    public function parcelsOf(Consignment $consignment): array
    {
        return $consignment->parcels->map(fn ($parcel) => ConsignmentParcelData::from($parcel))->all();
    }

    /**
     * @return list<ConsignmentParcelData>
     */
    #[Fixed(ManualHydrationLoop::class)]
    public function listedParcelsOf(Consignment $consignment): array
    {
        return ConsignmentParcelData::collect($consignment->parcels->all());
    }
}
