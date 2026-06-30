<?php

namespace Shop\Payments;

use JesseGall\CodeCommandments\Detectors\Backend\WrappingWithoutCauseDetector;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Exceptions\IntegrationException;

/**
 * Charges through the gateway SDK, rethrowing a domain exception on failure but
 * forgetting to chain the SDK's own exception as the cause.
 */
final class PaymentGatewayClient
{
    #[Sinful(WrappingWithoutCauseDetector::class)]
    public function charge(string $token, int $amountCents): string
    {
        try {
            return $this->callSdk($token, $amountCents);
        } catch (\Throwable $failure) {
            throw new IntegrationException($token);
        }
    }

    private function callSdk(string $token, int $amountCents): string
    {
        return "txn-{$token}";
    }

    public function supports(string $currency): bool
    {
        return in_array($currency, ['EUR', 'USD', 'GBP'], true);
    }
}
