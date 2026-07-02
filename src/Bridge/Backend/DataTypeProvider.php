<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Bridge\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Spatie\SpatieDataNode;
use JesseGall\CodeCommandments\Bridge\TypeContract;

/**
 * Publishes one {@see TypeContract} per Spatie `Data` class — its short name and the
 * public fields it exposes. That is the shape the frontend can generate from
 * (`#[TypeScript]`), so a hand-written TS type mirroring it is a duplicated contract.
 *
 * Composes the SAME fluent query a Spatie detector does — `whereClass()` narrowed by
 * `SpatieDataNode::isDataClass()` — so the `Data` FQCN stays stated once, on the node.
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
