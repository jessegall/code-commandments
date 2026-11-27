<?php

namespace Shop\Reaches;

use Illuminate\Http\Request;
use JesseGall\CodeCommandments\Testing\Fixed;
use Symfony\Component\HttpFoundation\HeaderBag;

#[Fixed(RepeatedReach::class)]
final readonly class ParcelCall
{
    public const string CARRIER = 'X-Shop-Carrier';

    private function __construct(private HeaderBag $headers) {}

    public static function of(Request $request): self
    {
        return new self($request->headers);
    }

    public function carrier(): string
    {
        return (string) $this->headers->get(self::CARRIER);
    }

    public function tracking(): TrackingCode
    {
        return TrackingCode::parse((string) $this->headers->get(TrackingCode::HEADER));
    }
}
