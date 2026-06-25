<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Backend\Sinful\ShouldBeEnums;

enum PortDirection: string
{
    case Input = 'input';
    case Output = 'output';
    case Bidirectional = 'bidirectional';
}
