<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes\Php;

use JesseGall\CodeCommandments\Support\Pipes\Pipe;
use PhpParser\Node;

/**
 * Filter to only Laravel controllers.
 *
 * @implements Pipe<PhpContext, PhpContext>
 */
final class FilterLaravelController implements Pipe
{
    public function handle(mixed $input): mixed
    {
        $controllers = array_values(array_filter(
            $input->classes,
            fn (Node\Stmt\Class_ $class) => $this->isController($class)
        ));

        return $input->with(classes: $controllers);
    }

    private function isController(Node\Stmt\Class_ $class): bool
    {
        // Check class name ends with Controller
        $className = $class->name?->toString() ?? '';
        if (str_ends_with($className, 'Controller')) {
            return true;
        }

        // Check if extends Controller
        if ($class->extends !== null) {
            $parentName = $class->extends->toString();

            return str_ends_with($parentName, 'Controller');
        }

        return false;
    }
}
