<?php

namespace Shop\Audit;

use JesseGall\CodeCommandments\Sins\Backend\Laravel\BareScopeEscape;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Models\Scopes\CurrentShopScope;
use Shop\Models\Shipment;

/**
 * Tallies shipments per shipping method across every shop for the yearly audit.
 */
final class ShipmentCensus
{
    #[Sinful(BareScopeEscape::class)]
    public function perMethod(): array
    {
        return Shipment::withoutGlobalScopes()
            ->selectRaw('method, count(*) as total')
            ->groupBy('method')
            ->pluck('total', 'method')
            ->all();
    }

    #[Fixed(BareScopeEscape::class)]
    public function total(): int
    {
        return Shipment::withoutGlobalScope(CurrentShopScope::class)->count();
    }
}
