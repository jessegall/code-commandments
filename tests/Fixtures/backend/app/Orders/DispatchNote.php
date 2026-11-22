<?php

namespace Shop\Orders;

use JesseGall\CodeCommandments\Testing\Righteous;
use Illuminate\Support\Str;
use JesseGall\CodeCommandments\Sins\Backend\DuplicateFunction;

final class DispatchNote
{
    public readonly string $code;

    #[Righteous(DuplicateFunction::class)]
    public function __construct(?string $code)
    {
        $this->code = $code ?? Str::uuid()->toString();
    }
}
