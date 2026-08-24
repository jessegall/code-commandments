<?php

namespace Shop\Loyalty;

use JesseGall\CodeCommandments\Sins\Backend\DivergentTwin;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Catalog\SupplierFeed;

/**
 * Reads the loyalty roster the same way the supplier feed is read, and never asks whether the drop
 * opened — so a missing roster reads as a fatal in the middle of the run rather than an empty one.
 */
final class MemberRoster
{
    public function __construct(private readonly SupplierFeed $feed) {}

    #[Sinful(DivergentTwin::class)]
    public function rows(string $path): array
    {
        $handle = fopen($path, 'r');

        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = rtrim((string) $row[0]);
        }

        fclose($handle);

        return $rows;
    }

    /**
     * The same roster, read through the one place that knows how a drop is opened here.
     */
    #[Fixed(DivergentTwin::class)]
    public function members(string $path): array
    {
        return $this->feed->rows($path);
    }
}
