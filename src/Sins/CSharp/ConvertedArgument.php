<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\PassTheObject;

final class ConvertedArgument extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'csharp-converted-argument',
            skill: PassTheObject::class,
            description: 'a scalar parameter its callers keep filling with the same conversion — `ReceiptFor(order.Id.ToString())` call after call — because it asks for the converted form instead of the value',
            rule: 'Declare the parameter in the type callers actually hold and convert inside — one rule about the conversion, in one place.',
            suggestion: 'Move the conversion into the method and take what the callers had (`ReceiptFor(Order order)` or `ReceiptFor(int orderId)`); a caller that forgets the conversion can no longer pass the wrong thing.',
        );
    }
}
