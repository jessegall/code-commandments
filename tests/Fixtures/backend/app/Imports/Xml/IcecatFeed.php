<?php

namespace Shop\Imports\Xml;

use Shop\Imports\ImportFormat;

final class IcecatFeed implements ImportFormat
{
    public function extension(): string
    {
        return 'xml';
    }
}
