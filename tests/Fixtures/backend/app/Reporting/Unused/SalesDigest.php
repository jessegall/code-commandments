<?php

namespace Shop\Reporting\Unused;

use JesseGall\CodeCommandments\Sins\Backend\UnusedPrivateMember;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * The daily sales digest mailed to the shop owner.
 */
final class SalesDigest
{
    /**
     * @param  list<int>  $sales
     */
    public function __construct(private readonly array $sales) {}

    public function total(): int
    {
        return array_sum($this->sales);
    }

    public function best(): int
    {
        return array_reduce($this->sales, $this->larger(...), 0);
    }

    public function sorted(): array
    {
        $sales = $this->sales;
        usort($sales, [$this, 'descending']);

        return $sales;
    }

    #[Sinful(UnusedPrivateMember::class)]
    private function average(): float
    {
        return $this->sales === [] ? 0.0 : $this->total() / count($this->sales);
    }

    #[Righteous(UnusedPrivateMember::class)]
    private function larger(int $best, int $sale): int
    {
        return max($best, $sale);
    }

    #[Righteous(UnusedPrivateMember::class)]
    private function descending(int $left, int $right): int
    {
        return $right <=> $left;
    }
}

/**
 * The digest with only what it uses.
 */
#[Fixed(UnusedPrivateMember::class)]
final class SalesSummary
{
    /**
     * @param  list<int>  $sales
     */
    public function __construct(private readonly array $sales) {}

    public function total(): int
    {
        return array_sum($this->sales);
    }
}
