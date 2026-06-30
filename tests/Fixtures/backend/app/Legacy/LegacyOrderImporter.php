<?php

namespace Shop\Legacy;

use JesseGall\CodeCommandments\Sins\Backend\ArchaeologyComment;
use JesseGall\CodeCommandments\Sins\Backend\BloatedDocblock;
use JesseGall\CodeCommandments\Sins\Backend\DeNulledFinder;
use JesseGall\CodeCommandments\Sins\Backend\ManufacturedFakeFill;

use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Models\Customer;

/**
 * This class is responsible for importing legacy orders from the old system.
 * It was originally extracted from the monolith during the 2023 migration and
 * has been refactored several times since. It reads the legacy CSV export, maps
 * each row to a customer, and creates the order. Previously this logic lived in
 * the OrderController but was moved here to keep controllers thin.
 *
 * TODO: remove once the legacy importer is fully decommissioned.
 */
#[Sinful(ArchaeologyComment::class)]
#[Sinful(BloatedDocblock::class)]
final class LegacyOrderImporter
{
    // previously this returned an array, now it returns a Customer or null
    #[Sinful(DeNulledFinder::class)]
    public function findCustomer(string $email): ?Customer
    {
        // loop over all customers and find the matching one
        return Customer::query()->where('email', $email)->first();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    #[Sinful(ManufacturedFakeFill::class)]
    public function import(array $rows): void
    {
        foreach ($rows as $row) {
            $customer = $this->findCustomer($row['email'] ?? '');

            // changed from update() to direct assignment in v2
            if ($customer !== null) {
                $customer->imported = true;
                $customer->save();
            }
        }
    }

    public function emailKnown(string $email): bool
    {
        return $this->findCustomer($email)?->exists ?? false;
    }
}

/**
 * Imports legacy orders from the old CSV export.
 */
#[Righteous(BloatedDocblock::class)]
final class TidyOrderImporter
{
    public function import(string $email): void
    {
        Customer::query()->where('email', $email)->firstOrFail();
    }
}
