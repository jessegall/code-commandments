<?php

namespace Shop\Legacy;

use JesseGall\CodeCommandments\Detectors\Backend\ArchaeologyCommentDetector;
use JesseGall\CodeCommandments\Detectors\Backend\BloatedDocblockDetector;
use JesseGall\CodeCommandments\Detectors\Backend\DeNulledFinderDetector;
use JesseGall\CodeCommandments\Detectors\Backend\ManufacturedFakeFillDetector;
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
#[Sinful(ArchaeologyCommentDetector::class)]
#[Sinful(BloatedDocblockDetector::class)]
final class LegacyOrderImporter
{
    // previously this returned an array, now it returns a Customer or null
    #[Sinful(DeNulledFinderDetector::class)]
    public function findCustomer(string $email): ?Customer
    {
        // loop over all customers and find the matching one
        return Customer::query()->where('email', $email)->first();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    #[Sinful(ManufacturedFakeFillDetector::class)]
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
