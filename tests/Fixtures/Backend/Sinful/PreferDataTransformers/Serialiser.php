<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Backend\Sinful\PreferDataTransformers;

class Serialiser
{
    public function serialise(FooData $foo): array
    {
        return [
            'name' => $foo->name,
            'type' => $foo->type,
            'required' => $foo->required,
        ];
    }

    public function tiny(FooData $foo): array
    {
        return ['name' => $foo->name];
    }

    public function notData(\stdClass $x): array
    {
        return ['a' => $x->a, 'b' => $x->b, 'c' => $x->c];
    }
}
