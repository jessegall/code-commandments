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
    use MemoisedPerCodebase;

    /**
     * @param  array<string, list<string>>  $casesByEnum  enum FQCN => case backing values (as strings)
     */
    private function __construct(
        private readonly array $casesByEnum,
    ) {}

    /**
     * Index every backed enum in the tree. A whole-tree walk, so it is built ONCE per codebase and
     * shared — three detectors ask, and a focused view asks per file.
     */
    protected static function build(Codebase $codebase): static
    {
        $map = [];
        $finder = new NodeFinder;

        foreach ($codebase->files() as $file) {
            foreach ($finder->findInstanceOf($file->ast, Enum_::class) as $enum) {
                /**
                 * @var Enum_ $enum
                 */
                $fqcn = ($enum->namespacedName ?? null)?->toString() ?? $enum->name?->toString();

                if ($fqcn === null) {
                    continue;
                }

                $values = [];

                foreach ($enum->stmts as $stmt) {
                    if (! ($stmt instanceof EnumCase && $stmt->expr !== null)) {
                        continue;
                    }

                    $value = self::literal($stmt->expr);

                    if ($value !== null) {
                        $values[] = $value;
                    }
                }

                if (count($values) >= 2) {
                    $map[$fqcn] = $values;
                }
            }
        }

        return new self($map);
    }

    /**
     * Does this codebase declare a backed enum under $fqcn — one whose cases a loose literal could
     * be bypassing?
     */
    public function isIndexed(?string $fqcn): bool
    {
        return $fqcn !== null && isset($this->casesByEnum[ltrim($fqcn, '\\')]);
    }

    /**
     * Do these two-plus literals all belong to a single backed enum's cases — i.e.
     * are they an enum's values spelled out as loose strings?
     *
     * A NUMBER is never evidence. `'pending'`/`'shipped'` are names, and finding them
     * loose beside an enum that owns them says the enum was bypassed; `0`/`1` are
     * quantities that belong to every pluralisation, index test and retry counter in
     * a codebase, so an int-backed enum whose ordinals happen to start at 0 would
     * mirror all of them. Nothing in the AST separates a count from a case ordinal —
     * only the author's intent does — so numeric literals are dropped before the
     * comparison, and a set with fewer than two names left over mirrors nothing.
     *
     * @param  list<string>  $literals
     */
    public function mirroredBy(array $literals): bool
    {
        $literals = array_unique(array_filter($literals, static fn (string $literal): bool => ! is_numeric($literal)));

        if (count($literals) < 2) {
            return false;
        }

        foreach ($this->casesByEnum as $cases) {
            if (array_diff($literals, $cases) === []) {
                return true;
            }
        }

        return false;
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
