<?php

namespace Shop\Dispatch;

/**
 * A bay a crate is loaded from: free until something stands in it, and blocked while the floor in
 * front of it is being swept.
 */
final class LoadingBay
{
    private ?Crate $standing = null;

    public function __construct(public readonly string $number, private bool $swept = true) {}

    public function take(Crate $crate): void
    {
        $this->standing = $crate;
    }

    public function isFree(): bool
    {
        return $this->standing === null;
    }

    public function isBlocked(): bool
    {
        return $this->swept === false;
    }
}
