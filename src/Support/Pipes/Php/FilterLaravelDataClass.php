<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes\Php;

use JesseGall\CodeCommandments\Support\Pipes\Pipe;
use PhpParser\Node;

/**
 * Filter to only Spatie Laravel Data classes.
 *
 * Only includes classes that extend Spatie\LaravelData\Data or Spatie\LaravelData\Resource.
 *
 * @implements Pipe<PhpContext, PhpContext>
 */
final class FilterLaravelDataClass implements Pipe
{
    /**
     * Known Spatie Data base classes.
     */
    private const SPATIE_DATA_CLASSES = [
        'Spatie\\LaravelData\\Data',
        'Spatie\\LaravelData\\Resource',
    ];

    public function handle(mixed $input): mixed
    {
        $useStatements = $input->useStatements ?? [];

        $dataClasses = array_values(array_filter(
            $input->classes,
            fn (Node\Stmt\Class_ $class) => $this->isSpatieDataClass($class, $useStatements)
        ));

        return $input->with(classes: $dataClasses);
    }

    /**
     * @param array<string, string> $useStatements
     */
    private function isSpatieDataClass(Node\Stmt\Class_ $class, array $useStatements): bool
    {
        if ($class->extends === null) {
            return false;
        }

        $parentName = $class->extends->toString();

        // Check if directly extends a Spatie Data class (fully qualified)
        foreach (self::SPATIE_DATA_CLASSES as $spatieClass) {
            if ($parentName === $spatieClass || str_ends_with($parentName, '\\' . class_basename($spatieClass))) {
                return true;
            }
        }

        // Check if the parent is imported via use statement
        $resolvedParent = $useStatements[$parentName] ?? null;
        if ($resolvedParent !== null) {
            foreach (self::SPATIE_DATA_CLASSES as $spatieClass) {
                if ($resolvedParent === $spatieClass) {
                    return true;
                }
            }
        }

        // Check if extends 'Data' or 'Resource' and there's a matching Spatie import
        if ($parentName === 'Data' && isset($useStatements['Data'])) {
            return $useStatements['Data'] === 'Spatie\\LaravelData\\Data';
        }

        if ($parentName === 'Resource' && isset($useStatements['Resource'])) {
            return $useStatements['Resource'] === 'Spatie\\LaravelData\\Resource';
        }

        return false;
    }
}
