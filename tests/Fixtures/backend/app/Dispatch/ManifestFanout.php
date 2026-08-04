<?php

namespace Shop\Dispatch;

use JesseGall\CodeCommandments\Sins\Backend\CoalescedLoopSubject;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Fans a manifest out per carrier — and asks, in the loop header, whether the caller handed it
 * anything for that carrier at all. The righteous twin (`fanOutGuarded`) states that at the
 * door; `pending()` shows the same `?? []` over the object's OWN state, which is not the sin.
 */
final class ManifestFanout
{
    /**
     * @var array<string, array<int, string>>
     */
    private array $queued = [];

    /**
     * @param  array<string, array<int, string>>  $manifest
     */
    #[Sinful(CoalescedLoopSubject::class)]
    public function fanOut(string $carrier, array $manifest): void
    {
        foreach ($manifest[$carrier] ?? [] as $parcel) {
            $this->queued[$carrier][] = $parcel;
        }
    }

    /**
     * @param  array<string, array<int, string>>  $manifest
     */
    #[Fixed(CoalescedLoopSubject::class)]
    #[Righteous(CoalescedLoopSubject::class)]
    public function fanOutGuarded(string $carrier, array $manifest): void
    {
        if (! isset($manifest[$carrier])) {
            return;
        }

        foreach ($manifest[$carrier] as $parcel) {
            $this->queued[$carrier][] = $parcel;
        }
    }

    /**
     * @return array<int, string>
     */
    public function pending(string $carrier): array
    {
        $labels = [];

        foreach ($this->queued[$carrier] ?? [] as $parcel) {
            $labels[] = strtoupper($parcel);
        }

        return $labels;
    }
}
