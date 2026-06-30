<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Exceptions;

final class MessageAtThrow extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'message-at-throw',
            skill: Exceptions::class,
            description: "Message string built at the throw site (no domain values / named factory)"
        );
    }
}
