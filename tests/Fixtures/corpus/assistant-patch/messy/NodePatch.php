<?php

namespace App\AssistantPatch;

use App\Support\FromArrayOnly;
use JesseGall\PhpTypes\T_Array;
use Spatie\LaravelData\Data;

/**
 * The partial node patch carried by an `update_node` action: an optional new
 * name plus the (possibly empty) input/output port lists to apply.
 *
 * @param  list<mixed>  $inputs
 * @param  list<mixed>  $outputs
 */
final class NodePatch extends Data
{
    use FromArrayOnly;

    public function __construct(
        public readonly ?string $name = null,
        public readonly array $inputs = T_Array::EMPTY,
        public readonly array $outputs = T_Array::EMPTY,
    ) {}
}
