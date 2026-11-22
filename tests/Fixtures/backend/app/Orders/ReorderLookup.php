<?php

namespace Shop\Orders;

use Illuminate\Database\ConnectionInterface;
use JesseGall\CodeCommandments\Sins\Backend\ManufacturedFakeFill;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Finds the order a customer asks to reorder, by the id their form posted.
 */
final class ReorderLookup
{
    public function __construct(private ConnectionInterface $database) {}

    /**
     * @param  array<string, mixed>  $posted
     */
    #[Sinful(ManufacturedFakeFill::class)]
    public function previous(array $posted): ?object
    {
        return $this->database->table('orders')->find((int) ($posted['order'] ?? 0));
    }
}
