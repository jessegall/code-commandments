<?php

namespace Shop\Reporting;

use Illuminate\Container\Container;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\ContainerReach;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * The label on an archived report, reaching for its stamp through the global container.
 */
final class ArchiveLabel
{
    #[Sinful(ContainerReach::class)]
    public function heading(string $covered): string
    {
        return 'Archive ' . Container::getInstance()->make(ArchiveStamp::class)->day($covered);
    }
}
