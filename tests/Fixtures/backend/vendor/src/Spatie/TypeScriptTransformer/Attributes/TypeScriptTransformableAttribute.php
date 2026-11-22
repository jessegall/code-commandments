<?php

namespace Spatie\TypeScriptTransformer\Attributes;

interface TypeScriptTransformableAttribute
{
    public function getType(): \phpDocumentor\Reflection\Type;
}
