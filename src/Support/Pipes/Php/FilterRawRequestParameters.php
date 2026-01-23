<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes\Php;

use JesseGall\CodeCommandments\Support\Pipes\Pipe;
use ReflectionClass;

/**
 * Filter method parameters to only raw Illuminate\Http\Request types.
 *
 * Excludes FormRequest subclasses which are acceptable.
 *
 * @implements Pipe<PhpContext, PhpContext>
 */
final class FilterRawRequestParameters implements Pipe
{
    public function handle(mixed $input): mixed
    {
        $filtered = array_values(array_filter(
            $input->parameters,
            fn ($param) => $this->isRawRequest($param['fqcn'], $param['type'])
        ));

        return $input->with(parameters: $filtered);
    }

    /**
     * Check if the type is raw Illuminate\Http\Request (not a FormRequest subclass).
     */
    private function isRawRequest(string $fqcn, string $typeName): bool
    {
        // Try reflection first
        if (class_exists($fqcn)) {
            try {
                $reflection = new ReflectionClass($fqcn);

                // It's raw request if it IS Illuminate\Http\Request
                // but NOT a subclass of FormRequest
                $isHttpRequest = $fqcn === 'Illuminate\\Http\\Request'
                    || $reflection->isSubclassOf('Illuminate\\Http\\Request')
                    || $reflection->getName() === 'Illuminate\\Http\\Request';

                $isFormRequest = $reflection->isSubclassOf('Illuminate\\Foundation\\Http\\FormRequest');

                return $isHttpRequest && ! $isFormRequest;
            } catch (\ReflectionException) {
                // Fall through to string matching
            }
        }

        // Fall back to string matching
        $shortName = basename(str_replace('\\', '/', $fqcn));

        return $shortName === 'Request'
            || $fqcn === 'Illuminate\\Http\\Request';
    }
}
