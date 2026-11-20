<?php

namespace Shop\Orders;

use Illuminate\Support\Str;
use JesseGall\CodeCommandments\Sins\Backend\DuplicateFunction;
use JesseGall\CodeCommandments\Testing\Sinful;

final class DispatchNote
{
    public readonly string $code;

    #[Sinful(DuplicateFunction::class)]
    public function __construct(?string $code)
    {
        $this->code = $code ?? Str::uuid()->toString();
    }
}
