<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures;

enum EnumWithCompareSelf: string
{
    use CompareSelf;

    case Array = 'array';

    case StringType = 'string';
}
