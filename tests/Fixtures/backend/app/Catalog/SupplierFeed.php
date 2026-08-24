<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Sins\Backend\DivergentTwin;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Reads the nightly supplier feed off disk. The feed is dropped by an external process, so the handle
 * is checked before it is read: an unreadable drop is an empty night, not a fatal.
 */
final class SupplierFeed
{
    #[Sinful(DivergentTwin::class)]
    public function rows(string $path): array
    {
        $handle = fopen($path, 'r');

        if (! is_resource($handle)) {
            return [];
        }

        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = rtrim((string) $row[0]);
        }

        fclose($handle);

        return $rows;
    }
}
