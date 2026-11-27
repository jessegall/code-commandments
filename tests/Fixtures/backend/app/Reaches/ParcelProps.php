<?php

namespace Shop\Reaches;

use Illuminate\Http\Request;
use JesseGall\CodeCommandments\Testing\Sinful;

final readonly class ParcelProps
{
    public function __construct(private CarrierBook $carriers) {}

    #[Sinful(RepeatedReach::class)]
    public function of(Request $request): array
    {
        $tracking = TrackingCode::parse((string) $request->headers->get(TrackingCode::HEADER));
        $carrier = $this->carriers->named((string) $request->headers->get(ParcelCall::CARRIER));

        return ['parcel' => $carrier->track($tracking)];
    }
}
