<?php

namespace Shop\Reporting;

use JesseGall\CodeCommandments\Sins\Backend\RepeatedFolderPath;
use JesseGall\CodeCommandments\Testing\Sinful;

final class PayoutCleanup
{
    public function __construct(private int $keepDays = 30) {}

    #[Sinful(RepeatedFolderPath::class)]
    public function purge(int $now): int
    {
        $removed = 0;
        $folder = storage_path('exports/payouts/');
        foreach (scandir($folder) ?: [] as $name) {
            $age = $now - (int) filemtime($folder . $name);
            if ($age > $this->keepDays * 86400 && unlink($folder . $name)) {
                $removed++;
            }
        }

        return $removed;
    }
}
