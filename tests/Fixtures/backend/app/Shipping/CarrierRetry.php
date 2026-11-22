<?php

namespace Shop\Shipping;

use JesseGall\CodeCommandments\Sins\Backend\SwallowCatch;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Books a consignment with its carrier. The first method swallows every refusal the carrier can make, a lapsed
 * contract among them; the second leaves only the one refusal it expects.
 */
final class CarrierRetry
{
    #[Sinful(SwallowCatch::class)]
    public function book(string $reference): void
    {
        try {
            $this->send($reference);
        } catch (CarrierFailure $failure) {
        }
    }

    #[Righteous(SwallowCatch::class)]
    public function bookUnlessLapsed(string $reference): void
    {
        try {
            $this->send($reference);
        } catch (CarrierContractLapsed $lapsed) {
        }
    }

    private function send(string $reference): void
    {
        if ($reference === '') {
            throw CarrierContractLapsed::for($reference);
        }
    }
}
