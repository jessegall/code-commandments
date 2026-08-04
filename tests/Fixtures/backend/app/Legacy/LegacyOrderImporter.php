<?php

namespace Shop\Legacy;

use JesseGall\CodeCommandments\Sins\Backend\ArchaeologyComment;
use JesseGall\CodeCommandments\Sins\Backend\BloatedDocblock;
use JesseGall\CodeCommandments\Sins\Backend\DeNulledFinder;
use JesseGall\CodeCommandments\Sins\Backend\ManufacturedFakeFill;

use JesseGall\CodeCommandments\Testing\Fixed;
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

    /**
     * The FIX: a row with no email is an absence at the SOURCE, so the boundary names the failure and
     * throws. The `?? ''` version looked up "the customer whose email is the empty string" and carried
     * that fake all the way to the import — the throw stops the row here, where the truth is known.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    #[Fixed(ManufacturedFakeFill::class)]
    public function importStrictly(array $rows): void
    {
        foreach ($rows as $row) {
            $email = $row['email'] ?? throw new MissingImportEmail();

            $this->findCustomer($email)?->markImported();
        }
    }

    public function emailKnown(string $email): bool
    {
        return $this->findCustomer($email)?->exists ?? false;
    }
}

/**
 * The named failure the strict import throws — the row is refused where it is read.
 */
final class MissingImportEmail extends \RuntimeException {}

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
