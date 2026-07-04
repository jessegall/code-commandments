<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Bridge\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Spatie\SpatieDataNode;
use JesseGall\CodeCommandments\Bridge\TypeContract;

/**
 * Publishes TypeContracts from Spatie Data classes using the same fluent query
 * pattern as detectors to keep the Data FQCN stated once.
 */
final class DataTypeProvider implements ContractProvider
{
    public function contracts(Codebase $codebase): array
    {
        $contracts = [];

        $classes = $codebase
            ->whereClass()
            ->where(static fn (SpatieDataNode $node): bool => $node->isDataClass())
            ->get();

        foreach ($classes as $class) {
            $name = $class->enclosingClassName();
            $fields = $class->publicFieldNames();

            if ($name !== null && $fields !== []) {
                $contracts[] = new TypeContract(self::shortName($name), $fields);
            }
        }

        return $contracts;
    }

    private static function shortName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts);
    }
}
