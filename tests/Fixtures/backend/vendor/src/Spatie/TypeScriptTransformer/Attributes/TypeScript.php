<?php

namespace Spatie\TypeScriptTransformer\Attributes;

#[\Attribute(127)]
class TypeScript
{
    public ?string $name;
    public function __construct(?string $name = NULL) {}
}
