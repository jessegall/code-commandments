<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures;

enum PlainEnumNoCompareSelf: string
{
    case Array = 'array';

    case StringType = 'string';
}
