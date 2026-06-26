<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

use JesseGall\CodeCommandments\Ast\Codebase;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\NodeFinder;

/**
 * Indexes the backed enums in a codebase by their case values — so a detector can
 * ask "is this set of string/int literals really an enum's cases?". Only backed
 * enums with ≥2 cases are indexed (a meaningful closed set).
 */
final class Enums
{
    /**
     * @return array<string, list<string>>  enum FQCN => case backing values (as strings)
     */
    public static function casesByEnum(Codebase $codebase): array
    {
        $map = [];
        $finder = new NodeFinder;

        foreach ($codebase->files() as $file) {
            foreach ($finder->findInstanceOf($file->ast, Enum_::class) as $enum) {
                /** @var Enum_ $enum */
                $fqcn = ($enum->namespacedName ?? null)?->toString() ?? $enum->name?->toString();

                if ($fqcn === null) {
                    continue;
                }

                $values = [];

                foreach ($enum->stmts as $stmt) {
                    if ($stmt instanceof EnumCase && $stmt->expr !== null) {
                        $value = self::literal($stmt->expr);

                        if ($value !== null) {
                            $values[] = $value;
                        }
                    }
                }

                if (count($values) >= 2) {
                    $map[$fqcn] = $values;
                }
            }
        }

        return $map;
    }

    private static function literal(object $expr): ?string
    {
        return match (true) {
            $expr instanceof String_ => $expr->value,
            $expr instanceof Int_ => (string) $expr->value,
            default => null,
        };
    }
}
