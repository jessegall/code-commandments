<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Bridge;

/**
 * A type shape one engine OWNS and publishes across the {@see Bridge} — a name plus
 * its field names. The backend derives one per Spatie `Data` class; the frontend
 * asks each hand-written TS type "does a published contract MIRROR me?" so it can
 * flag the duplicate.
 *
 * Matching is spelling-insensitive on BOTH the name and the fields: a name and its
 * fields are compared by their {@see canonical} form (lowercased, separators
 * dropped), so `first_name`, `firstName` and `FirstName` are one field. A candidate
 * MIRRORS this contract when the names canonicalise equal AND the field sets overlap
 * by at least {@see MIN_OVERLAP} (Jaccard) — a near-copy that has drifted by a field
 * or two still counts; an unrelated shape that merely shares a couple of names does
 * not.
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
     */
    public function __construct(
        public readonly string $name,
        public readonly array $fields,
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

    private function sameName(string $name): bool
    {
        return self::canonical($name) === self::canonical($this->name);
    }

    /**
     * The Jaccard overlap of this contract's fields with $fields, both canonicalised —
     * `|shared| / |combined|`, in `[0, 1]`. Zero when either side is empty.
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
        $combined = count($mine + $theirs);

        return $shared / $combined;
    }

    /**
     * The canonical form of an identifier for spelling-insensitive comparison —
     * lowercased with `_`/`-` separators dropped, so the snake, camel, Pascal and
     * kebab spellings of one name collapse to a single key.
     */
    private static function canonical(string $identifier): string
    {
        return str_replace(['_', '-'], '', strtolower($identifier));
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
            $set[self::canonical($field)] = true;
        }

        return $set;
    }
}
