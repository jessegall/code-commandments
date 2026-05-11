<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Backend\Sinful\StringsThatShouldBeEnumsCrossFile;

enum MirroringAction: string
{
    case Publish = 'publish';
    case Unpublish = 'unpublish';
}
