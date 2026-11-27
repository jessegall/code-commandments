<?php

namespace Shop\Reaches;

use Illuminate\Http\Request;
use JesseGall\CodeCommandments\Testing\Fixed;

final readonly class ParcelLookup
{
    public function __construct(private CarrierBook $carriers) {}

    #[Fixed(RepeatedReach::class)]
    public function of(Request $request): array
    {
        $call = ParcelCall::of($request);

        return ['parcel' => $this->carriers->named($call->carrier())->track($call->tracking())];
    }
}
