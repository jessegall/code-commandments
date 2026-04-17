<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Backend\Sinful;

/**
 * A service that receives raw arrays and picks fields by string key.
 * The DTO should replace the `array $row` parameter.
 */
class ArrayStringIndexingOnParam
{
    public function process(array $row): string
    {
        $nodeId = $row['nodeId'];
        $port = $row['port'];
        $label = $row['label'];

        return $nodeId . ':' . $port . ':' . $label;
    }
}
