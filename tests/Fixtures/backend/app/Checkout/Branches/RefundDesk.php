<?php

namespace Shop\Checkout\Branches;

use JesseGall\CodeCommandments\Sins\Backend\DuplicatedBranchTail;
use JesseGall\CodeCommandments\Sins\Backend\UnusedPrivateMember;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Refunds an order to the card or to store credit, and notes every refund in the desk's log.
 */
final class RefundDesk
{
    /**
     * @var list<string>
     */
    #[Sinful(UnusedPrivateMember::class)]
    private array $log = [];

    #[Sinful(DuplicatedBranchTail::class)]
    public function refund(int $orderId, int $cents): void
    {
        if ($cents <= 5000) {
            $this->log[] = "card {$cents}";
            $this->note($orderId);
        } else {
            $this->log[] = "credit {$cents}";
            $this->note($orderId);
        }
    }

    #[Fixed(DuplicatedBranchTail::class)]
    public function refundOnce(int $orderId, int $cents): void
    {
        $this->log[] = ($cents <= 5000 ? 'card ' : 'credit ') . $cents;
        $this->note($orderId);
    }

    #[Righteous(DuplicatedBranchTail::class)]
    public function refundLarge(int $orderId, int $cents): void
    {
        if ($cents > 10000) {
            $this->log[] = "review {$cents}";
            $this->note($orderId);
        }
    }

    private function note(int $orderId): void
    {
        $this->log[] = "order {$orderId}";
    }
}
