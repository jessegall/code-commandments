<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes\Php;

use JesseGall\CodeCommandments\Support\Pipes\Pipe;
use JesseGall\PhpTypes\T_String;

/**
 * Filter method parameters to only service types.
 *
 * Removes parameters that are allowed for method injection:
 * - Request/FormRequest objects
 * - Eloquent Models (route model binding)
 * - Enums
 *
 * @implements Pipe<PhpContext, PhpContext>
 */
final class FilterServiceType implements Pipe
{
    public function handle(mixed $input): mixed
    {
        $filtered = collect($input->parameters)
            ->filter(fn ($param) => TypeChecker::isServiceType($param['fqcn'] ?? T_String::empty()))
            ->values()
            ->all();

        return $input->with(parameters: $filtered);
    }
}
