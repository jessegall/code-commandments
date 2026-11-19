<?php

namespace Shop\Lockers;

use JesseGall\CodeCommandments\Sins\Backend\RepeatedFolderPath;
use JesseGall\CodeCommandments\Testing\Righteous;

final class LockerNotice
{
    #[Righteous(RepeatedFolderPath::class)]
    public function body(string $locker): string
    {
        return str_replace('{locker}', $locker, (string) file_get_contents(__DIR__ . '/stubs/notice.stub'));
    }
}
