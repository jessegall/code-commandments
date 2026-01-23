<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes\Php;

use JesseGall\CodeCommandments\Support\Pipes\Pipe;
use PhpParser\Node;

/**
 * Filter to only FormRequest classes.
 *
 * @implements Pipe<PhpContext, PhpContext>
 */
final class FilterFormRequestClass implements Pipe
{
    public function handle(mixed $input): mixed
    {
        $requestClasses = array_values(array_filter(
            $input->classes,
            fn (Node\Stmt\Class_ $class) => $this->isFormRequestClass($class)
        ));

        return $input->with(classes: $requestClasses);
    }

    private function isFormRequestClass(Node\Stmt\Class_ $class): bool
    {
        // Check class name ends with Request
        $className = $class->name?->toString() ?? '';
        if (str_ends_with($className, 'Request') && $className !== 'Request') {
            return true;
        }

        // Check if extends FormRequest or Request
        if ($class->extends !== null) {
            $parentName = $class->extends->toString();

            return str_ends_with($parentName, 'FormRequest')
                || ($parentName === 'Request' || str_ends_with($parentName, '\\Request'));
        }

        return false;
    }
}
