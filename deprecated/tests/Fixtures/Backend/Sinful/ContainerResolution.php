<?php

namespace App\Services;

use Illuminate\Support\Facades\App;

class InvoiceService
{
    public function generate(int $orderId): mixed
    {
        // Service-locator: hidden dependency, hard to test.
        $generator = app(InvoiceGenerator::class);

        // Same pattern, different shape.
        $mailer = resolve(Mailer::class);

        // Chained on app().
        $logger = app()->make(InvoiceLogger::class);

        // Facade form.
        $cache = App::make(InvoiceCache::class);

        return [$generator, $mailer, $logger, $cache];
    }
}
