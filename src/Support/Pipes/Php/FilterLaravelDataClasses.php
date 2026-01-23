<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes\Php;

use JesseGall\CodeCommandments\Support\Pipes\Pipe;
use PhpParser\Node;

/**
 * Filter to only Laravel Data classes.
 *
 * @implements Pipe<PhpContext, PhpContext>
 */
final class FilterLaravelDataClasses implements Pipe
{
    public function handle(mixed $input): mixed
    {
        $dataClasses = array_values(array_filter(
            $input->classes,
            fn (Node\Stmt\Class_ $class) => $this->isDataClass($class)
        ));

        return $input->with(classes: $dataClasses);
    }

    private function isDataClass(Node\Stmt\Class_ $class): bool
    {
        // Check class name ends with Data
        $className = $class->name?->toString() ?? '';
        if (str_ends_with($className, 'Data')) {
            return true;
        }

        // Check if extends Data
        if ($class->extends !== null) {
            $parentName = $class->extends->toString();

            return str_ends_with($parentName, 'Data');
        }

        return false;
    }
}
