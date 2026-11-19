<?php

namespace Shop\Tills;

/**
 * One printed receipt: the run's header printer heads the lines the till rang up.
 */
final class ReceiptSlip
{
    public function __construct(private readonly ReceiptHeaders $headers) {}

    /**
     * @param  list<string>  $lines
     */
    public function printed(int $receipt, array $lines): string
    {
        return implode("\n", [$this->headers->header($receipt), ...$lines]);
    }
}
