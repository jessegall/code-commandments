<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Bridge;

use JesseGall\CodeCommandments\Support\Name;

/**
 * A type shape published across the Bridge (name + fields), matched spelling-insensitively
 * via canonical form; a candidate mirrors this when names canonicalise equal and field overlap
 * meets the MIN_OVERLAP threshold.
 */
final class TypeContract implements Contract
{
    /**
     * The share of the UNION of the two field sets that must coincide for a candidate
     * to count as a mirror — calibrated to catch a duplicate that has drifted by a
     * field or two without flagging a shape that merely overlaps.
     */
    private const float MIN_OVERLAP = 0.8;

    /**
     * @param  list<string>  $fields
     * @param  list<string>  $optionalFields  the subset of $fields the server OMITS from the wire when
     *                                          absent (a `T|Optional` slot) — a mirror may leave these out
     */
    public function __construct(
        public readonly string $name,
        public readonly array $fields,
        public readonly array $optionalFields = [],
    ) {}

    /**
     * Does a hand-written type named $name with these $fields mirror this contract —
     * same name (spelling-insensitive) and a field overlap at or above the floor?
     *
     * @param  list<string>  $fields
     */
    public function mirroredBy(string $name, array $fields): bool
    {
        return $this->sameName($name)
            && $this->fieldOverlap($fields) >= self::MIN_OVERLAP;
    }

    /**
     * Does the type named $name — this one — publish $field on the wire? What a rule asks when it
     * holds a declaration and needs to know whether the value it makes ever leaves the server.
     */
    public function publishes(string $name, string $field): bool
    {
        return $this->sameName($name) && isset(self::canonicalSet($this->fields)[Name::canonical($field)]);
    }

    private function sameName(string $name): bool
    {
        return Name::canonical($name) === Name::canonical($this->name);
    }

    /**
     * The Jaccard overlap of this contract's fields with $fields, both canonicalised —
     * `|shared| / |combined|`, in `[0, 1]`. Zero when either side is empty. An OPTIONAL
     * contract field the candidate omits (a `T|Optional` slot Spatie drops from the wire)
     * is legitimately absent, so it is excluded from the union rather than counted as drift.
     *
     * @param  list<string>  $fields
     */
    private function fieldOverlap(array $fields): float
    {
        $mine = self::canonicalSet($this->fields);
        $theirs = self::canonicalSet($fields);

        if ($mine === [] || $theirs === []) {
            return 0.0;
        }

        $shared = count(array_intersect_key($mine, $theirs));
        $union = $mine + $theirs;

        foreach (self::canonicalSet($this->optionalFields) as $key => $_) {
            if (! isset($theirs[$key])) {
                unset($union[$key]);
            }
        }

        $combined = count($union);

        return $combined === 0 ? 0.0 : $shared / $combined;
    }

    /**
     * A set of canonical field names — keyed by the canonical form so duplicates
     * (two spellings of one field) collapse and intersection is a key lookup.
     *
     * @param  list<string>  $fields
     * @return array<string, true>
     */
    private static function canonicalSet(array $fields): array
    {
        $set = [];

        foreach ($fields as $field) {
            $set[Name::canonical($field)] = true;
        }

        return $set;
    }
}
