<?php

namespace Shop\Services;

use Illuminate\Support\Facades\Log;
use JesseGall\CodeCommandments\Detectors\Backend\ContainerReachDetector;
use JesseGall\CodeCommandments\Detectors\Backend\FacadeCallDetector;
use JesseGall\CodeCommandments\Detectors\Backend\GenericExceptionDetector;
use JesseGall\CodeCommandments\Detectors\Backend\MessageAtThrowDetector;
use JesseGall\CodeCommandments\Testing\Sinful;

final class PaymentProcessor
{
    #[Sinful(ContainerReachDetector::class)]
    #[Sinful(GenericExceptionDetector::class)]
    #[Sinful(FacadeCallDetector::class)]
    #[Sinful(MessageAtThrowDetector::class)]
    public function charge(string $token, int $amountCents): bool
    {
        $gateway = app(PaymentGatewayRegistry::class)->get('default');

        if ($amountCents <= 0) {
            throw new \RuntimeException("Cannot charge a non-positive amount: {$amountCents}");
        }

        Log::info('charging', ['amount' => $amountCents]);

        return $gateway->send($token, $amountCents);
    }

    #[Sinful(ContainerReachDetector::class)]
    public function capture(int $amountCents): void
    {
        $gateway = resolve(PaymentGatewayRegistry::class)->get('default');
        $gateway->capture($amountCents);
    }
}
