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

    // Reads >= 3 properties to DRIVE validation, returns error strings — not a
    // serialiser (#16). The output array's values do not derive from $foo.
    public function validate(FooData $foo): array
    {
        $errors = [];

        if ($foo->name === '') {
            $errors[] = "Field 'name' is required.";
        }

        if ($foo->type === '') {
            $errors[] = "Field 'type' must be set.";
        }

        if ($foo->required && $foo->name === '') {
            $errors[] = 'A required field needs a name.';
        }

        return $errors;
    }
}
