<?php

namespace Spatie\TypeScriptTransformer\Attributes;

#[\Attribute(127)]
class LiteralTypeScriptType implements \Spatie\TypeScriptTransformer\Attributes\TypeScriptTransformableAttribute
{
    private array|string $typeScript;
    public function __construct(array|string $typeScript) {}
    public function getType(): \phpDocumentor\Reflection\Type {}
}
