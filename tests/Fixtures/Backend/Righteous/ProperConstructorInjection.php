<?php

namespace App\Services;

class ProperInvoiceService
{
    public function __construct(
        private InvoiceGenerator $generator,
        private Mailer $mailer,
    ) {}

    public function generate(int $orderId): mixed
    {
        $invoice = $this->generator->for($orderId);

        $this->mailer->send($invoice);

        return $invoice;
    }
}
