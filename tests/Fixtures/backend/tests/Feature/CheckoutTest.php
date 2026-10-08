<?php

namespace Shop\Tests\Feature;

use Illuminate\Support\Facades\Log;
use JesseGall\CodeCommandments\Sins\Backend\ArrayBag;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\ConfigRead;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\ContainerReach;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\FacadeCall;
use JesseGall\CodeCommandments\Testing\Righteous;
use Shop\Services\PaymentProcessor;

final class CheckoutTest
{
    #[Righteous(ContainerReach::class)]
    #[Righteous(FacadeCall::class)]
    #[Righteous(ConfigRead::class)]
    public function test_a_payment_is_logged_in_the_shop_currency(): void
    {
        $processor = app(PaymentProcessor::class);
        $currency = config('shop.currency');

        $processor->charge('tok_test', 100);

        Log::info('charged in ' . $currency);
    }

    #[Righteous(ArrayBag::class)]
    public function checkTheEmittedPayload(array $payload): void
    {
        assert($payload['currency'] === 'EUR');
        assert($payload['amount_cents'] === 100);
    }
}
