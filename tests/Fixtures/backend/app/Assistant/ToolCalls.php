<?php

namespace Shop\Assistant;

use JesseGall\CodeCommandments\Sins\Backend\ArrayBagParameter;
use JesseGall\CodeCommandments\Sins\Backend\UnusedPrivateMember;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Asks the shop assistant's tools for what it needs: each tool takes arguments of its own, so the one array that
 * carries them is a map with no record shape, whatever keys a single call happens to write.
 */
final class ToolCalls
{
    /**
     * @var list<array{tool: string, arguments: array<string, mixed>}>
     */
    #[Sinful(UnusedPrivateMember::class)]
    private array $sent = [];

    public function openOrder(int $order): void
    {
        $this->call('orders.open', ['order' => $order]);
    }

    public function searchCatalog(string $words, int $limit): void
    {
        $this->call('catalog.search', ['query' => $words, 'limit' => $limit]);
    }

    public function restock(string $sku, int $units, bool $urgent): void
    {
        $this->call('stock.restock', ['sku' => $sku, 'units' => $units, 'urgent' => $urgent]);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    #[Righteous(ArrayBagParameter::class)]
    private function call(string $tool, array $arguments): void
    {
        $this->sent[] = ['tool' => $tool, 'arguments' => $arguments];
    }
}
