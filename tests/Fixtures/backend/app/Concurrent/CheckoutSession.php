<?php

namespace Shop\Concurrent;

use JesseGall\CodeCommandments\Sins\Backend\MemberAfterMethod;
use JesseGall\CodeCommandments\Sins\Backend\MemberOutOfOrder;
use JesseGall\CodeCommandments\Sins\Backend\NarratedCommand;
use JesseGall\CodeCommandments\Sins\Backend\RedundantArrowReturnType;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\Concurrent\Concurrent;

/**
 * Transient per-customer checkout state, shared across requests. Righteous twin: the TTL and the item
 * count stand at the head of the class, so one read of the top says everything this object holds.
 */
#[Righteous(MemberAfterMethod::class)]
#[Righteous(MemberOutOfOrder::class)]
#[Righteous(NarratedCommand::class)]
#[Righteous(RedundantArrowReturnType::class)]
final class CheckoutSession
{
    private const int TTL = 1800;

    public static int $started = 0;

    public string $currency = 'EUR';

    private int $itemCount = 0;

    public bool $isEmpty { get => $this->itemCount === 0; }

    /**
     * @return Concurrent<self>
     */
    public static function for(int $customerId): Concurrent
    {
        return new Concurrent(
            key: "checkout:{$customerId}",
            default: new self,
            ttl: self::TTL,
        );
    }

    /**
     * Righteous: one type WIDENS what the expression yields, the other annotates an expression
     * nothing here can prove. Both tell the reader something the code does not.
     */
    public function readers(): array
    {
        return [
            fn (): ?int => $this->itemCount,
            fn (): int => $this->itemCount > 0 ? $this->itemCount : 0,
        ];
    }

    public function addItem(): void
    {
        $this->itemCount++;
    }
}
