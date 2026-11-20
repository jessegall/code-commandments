<?php

namespace Shop\Tests\Feature;

use Illuminate\Support\Facades\Event;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\DeadEventWiring;
use JesseGall\CodeCommandments\Testing\Righteous;
use Shop\Events\StockReconciled;

final class StockEventsTest
{
    #[Righteous(DeadEventWiring::class)]
    public function test_a_reconciliation_announces_itself(): void
    {
        $heard = [];
        Event::listen(StockReconciled::class, function (StockReconciled $event) use (&$heard): void {
            $heard[] = $event;
        });

        assert($heard === []);
    }
}
