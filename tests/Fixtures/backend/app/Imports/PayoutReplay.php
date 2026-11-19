<?php

namespace Shop\Imports;

use JesseGall\CodeCommandments\Sins\Backend\RepeatedFolderPath;
use JesseGall\CodeCommandments\Testing\Sinful;

final class PayoutReplay
{
    /**
     * @return list<string>
     */
    #[Sinful(RepeatedFolderPath::class)]
    public function newestLines(): array
    {
        $folder = storage_path() . '/exports/payouts/';
        $files = scandir($folder, SCANDIR_SORT_DESCENDING);

        return $files === false || count($files) < 3 ? [] : file($folder . $files[0], FILE_IGNORE_NEW_LINES);
    }
}
