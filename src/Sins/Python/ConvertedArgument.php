<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\PassTheObject;

final class ConvertedArgument extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-converted-argument',
            skill: PassTheObject::class,
            description: 'a scalar parameter its callers keep filling with the same conversion — `receipt_for(str(order.id))` call after call — because it asks for the converted form instead of the value',
            rule: 'Declare the parameter in the type callers actually hold and convert inside — one rule about the conversion, in one place.',
            suggestion: 'Move the conversion into the function and take what the callers had (`receipt_for(order)` or `receipt_for(order_id: int)`); a caller that forgets the conversion can no longer pass the wrong thing.',
        );
    }
}
