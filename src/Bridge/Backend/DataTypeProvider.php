<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Bridge\Backend;

use JesseGall\CodeCommandments\Support\ClassName;

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
            $data = $codebase->wrap($class->node, $class->file, SpatieDataNode::class);
            $name = $data->enclosingClassName();
            $fields = $data->publicFieldNames();

            if ($name !== null && $fields !== []) {
                $contracts[] = new TypeContract(ClassName::short($name), $fields, $data->optionalPublicFieldNames());
            }
        }

        return $contracts;
    }
}
