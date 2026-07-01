<?php

namespace Shop\Services;

use JesseGall\CodeCommandments\Sins\Backend\Laravel\ContainerReach;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\FacadeCall;
use JesseGall\CodeCommandments\Sins\Backend\GenericException;
use JesseGall\CodeCommandments\Sins\Backend\MessageAtThrow;

use Illuminate\Support\Facades\Log;
use JesseGall\CodeCommandments\Testing\Sinful;

final class PaymentProcessor
{
    #[Sinful(ContainerReach::class)]
    #[Sinful(GenericException::class)]
    #[Sinful(FacadeCall::class)]
    #[Sinful(MessageAtThrow::class)]
    public function charge(string $token, int $amountCents): bool
    {
        $gateway = app(PaymentGatewayRegistry::class)->get('default');

        if ($amountCents <= 0) {
            throw new \RuntimeException("Cannot charge a non-positive amount: {$amountCents}");
        }

        Log::info('charging', ['amount' => $amountCents]);

        return $gateway->send($token, $amountCents);
    }

    #[Sinful(ContainerReach::class)]
    public function capture(int $amountCents): void
    {
        $gateway = resolve(PaymentGatewayRegistry::class)->get('default');
        $gateway->capture($amountCents);
    }
}
