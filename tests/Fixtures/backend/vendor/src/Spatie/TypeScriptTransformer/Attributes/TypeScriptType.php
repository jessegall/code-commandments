<?php

namespace Spatie\TypeScriptTransformer\Attributes;

#[\Attribute(127)]
class TypeScriptType implements \Spatie\TypeScriptTransformer\Attributes\TypeScriptTransformableAttribute
{
    private array|string $type;
    public function __construct(array|string $type) {}
    public function getType(): \phpDocumentor\Reflection\Type {}
}
