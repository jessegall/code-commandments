<?php

declare(strict_types=1);

namespace App\Inventory;

use Illuminate\Http\RedirectResponse;

/**
 * Accepts a request to reserve stock for a SKU.
 */
final class ReservationController
{
    public function __construct(
        private readonly ReservationService $reservations,
    ) {}

    public function store(ReservationRequest $request): RedirectResponse
    {
        $this->reservations->reserve(new AllocationRequest(
            sku: $request->sku(),
            quantity: $request->quantity(),
        ));

        return redirect()->route('inventory.index');
    }
}
