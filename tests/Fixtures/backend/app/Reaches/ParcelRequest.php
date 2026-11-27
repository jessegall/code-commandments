<?php

namespace Shop\Reaches;

use Illuminate\Http\Request;
use JesseGall\CodeCommandments\Testing\Righteous;

#[Righteous(RepeatedReach::class)]
final readonly class ParcelRequest
{
    public function __construct(private Request $request) {}

    public function carrier(): string
    {
        return (string) $this->request->headers->get(ParcelCall::CARRIER);
    }

    public function isTracked(): bool
    {
        $tracked = $this->request->headers->has(TrackingCode::HEADER);

        return $tracked && $this->carrier() !== '';
    }
}
