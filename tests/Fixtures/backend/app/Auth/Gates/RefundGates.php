<?php

namespace Shop\Auth\Gates;

use JesseGall\CodeCommandments\Sins\Backend\RefusedBequest;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * Whether a clerk may refund an order; a refusal is the gate's own outcome.
 */
interface RefundGate
{
    /**
     * @throws RefundDenied
     */
    public function allow(int $clerkId, int $cents): void;
}

/**
 * The gate while refunds are switched off: refusing is the contract's own answer, not a refused inheritance.
 */
final class ClosedRefundGate implements RefundGate
{
    #[Righteous(RefusedBequest::class)]
    public function allow(int $clerkId, int $cents): void
    {
        throw RefundDenied::for($clerkId);
    }
}

/**
 * The gate in normal trade: small refunds pass, large ones are denied.
 */
final class LimitRefundGate implements RefundGate
{
    public function allow(int $clerkId, int $cents): void
    {
        if ($cents > 10000) {
            throw RefundDenied::for($clerkId);
        }
    }
}

/**
 * A clerk not allowed to refund.
 */
final class RefundDenied extends \RuntimeException
{
    public static function for(int $clerkId): self
    {
        return new self("Clerk {$clerkId} may not refund this order.");
    }
}
