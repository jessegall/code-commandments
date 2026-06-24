<?php
namespace Acme\Notify\NullRight;

final class RateProvider
{
    public function __construct(private RateMemo $memo) {}

    // The single caller coalesces the miss to a fresh value — bare null is right
    // here (a cache miss), not a domain decision callers must each handle.
    public function rateFor(string $pair): float
    {
        return $this->memo->lookup($pair) ?? $this->refresh($pair);
    }

    private function refresh(string $pair): float { return 1.0; }
}
