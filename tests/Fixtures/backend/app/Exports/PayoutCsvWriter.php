<?php

namespace Shop\Exports;

use JesseGall\CodeCommandments\Sins\Backend\RepeatedFolderPath;
use JesseGall\CodeCommandments\Testing\Sinful;

final class PayoutCsvWriter
{
    /**
     * @param  list<list<string>>  $rows
     */
    #[Sinful(RepeatedFolderPath::class)]
    public function write(array $rows, string $day): string
    {
        $file = storage_path('exports/payouts/' . $day . '.csv');
        $handle = fopen($file, 'w');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        return $file;
    }
}
