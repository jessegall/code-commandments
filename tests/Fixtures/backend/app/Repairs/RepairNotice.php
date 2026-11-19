<?php

namespace Shop\Repairs;

use JesseGall\CodeCommandments\Sins\Backend\RepeatedFolderPath;
use JesseGall\CodeCommandments\Testing\Righteous;

final class RepairNotice
{
    #[Righteous(RepeatedFolderPath::class)]
    public function isCustomised(): bool
    {
        return filesize(__DIR__ . '/stubs/notice.stub') !== 412;
    }
}
