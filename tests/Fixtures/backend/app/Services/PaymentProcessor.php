<?php

namespace Shop\Services;

use JesseGall\CodeCommandments\Sins\Backend\Laravel\ContainerReach;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\FacadeCall;
use JesseGall\CodeCommandments\Sins\Backend\GenericException;
use JesseGall\CodeCommandments\Sins\Backend\MessageAtThrow;

use Illuminate\Support\Facades\Log;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;

final class PaymentProcessor
{
    #[Fixed(ContainerReach::class)]
    public function __construct(private readonly PaymentGatewayRegistry $gateways) {}

    // Righteous twin (ContainerReach): a class resolving ITSELF from a static factory
    // is the construction seam that gives a static entry point constructor DI — not a
    // dependency reach.
    public static function make(mixed ...$args): static
    {
        return app(static::class, $args);
    }

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

    /**
     * The registry is a declared constructor parameter, so the class states what it needs and the
     * container hands it over — nothing reaches back into the container from the body.
     */
    #[Fixed(ContainerReach::class)]
    public function captureAuthorised(int $amountCents): void
    {
        $this->gateways->get('default')->capture($amountCents);
    }
}
