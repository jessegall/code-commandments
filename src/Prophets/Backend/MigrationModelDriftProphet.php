<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Support\MigrationSchemaIndex;
use PhpParser\Node;
use PhpParser\NodeFinder;

/**
 * Flag a model whose table declares a non-trivially-typed column (`json` / `boolean`
 * / a non-timestamp datetime / `decimal`) that the model does NOT cast — so the
 * attribute is read back as a raw string (a json blob, `"0"`/`"1"`, a date string)
 * instead of the value the schema promises.
 *
 * Cross-artifact congruence (model ↔ migration), via {@see MigrationSchemaIndex}
 * (the migration-declared columns) ↔ the model's `$casts` / `casts()` / `$dates` /
 * accessors. Near-zero-FP: fires ONLY when the model's table is actually declared by
 * a migration (so the schema is ground truth), the model extends a framework base
 * (so casts are not inherited from a custom parent), and the column is not cast,
 * dated, accessor-backed, or an Eloquent auto-cast timestamp. ADVISORY (a WARNING).
 */
#[IntroducedIn('2.17.0')]
class MigrationModelDriftProphet extends PhpCommandment
{
    /** Type categories where a missing cast changes the read value. */
    private const TYPED = ['json', 'bool', 'datetime', 'decimal'];

    /** Eloquent casts these natively — never drift. */
    private const AUTO_CAST = ['created_at', 'updated_at', 'deleted_at'];

    /** Framework model bases — casts/dates declared on the model are the full set. */
    private const MODEL_BASES = ['model', 'authenticatable', 'pivot', 'morphpivot'];

    public function description(): string
    {
        return 'A typed migration column (json/bool/datetime/decimal) must have a matching model cast';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen(
                'A model\'s table declares a `json`/`boolean`/non-timestamp datetime/'
                . '`decimal` column (per its migration), but the model does NOT cast it '
                . '(no `$casts`/`casts()` entry, no `$dates`, no accessor). The attribute '
                . 'is then read back as a raw string, not the typed value the schema promises.'
            )
            ->leaveWhen(
                'the column is cast/dated/accessor-backed already; the model reads the raw '
                . 'value deliberately; the cast lives in a trait or custom base the model '
                . 'extends (this prophet only judges models on a framework base); or the '
                . 'column is added by a package outside the scanned migrations.'
            )
            ->whenUnsure(
                'add the cast — `protected function casts(): array { return [\'meta\' => '
                . '\'array\', \'active\' => \'boolean\', \'published_at\' => \'datetime\']; }` — so '
                . 'the attribute matches the schema. A json column with no cast is almost '
                . 'always a bug (you get a string, not an array).'
            );
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
A migration declares a column's type; the model declares how to cast it. When they
drift, the attribute is read back as a raw string — a `json` column becomes a JSON
string (not an array), a `boolean` becomes `"0"`/`"1"`, a datetime becomes a date
string — and code that expects the typed value breaks subtly.

Bad — migration says `$table->json('meta')` but the model casts nothing:
    class Order extends Model {
        protected $fillable = ['meta'];
        // $order->meta is a STRING, not an array
    }

Good — cast it to match the schema:
    protected function casts(): array { return ['meta' => 'array']; }

WHAT FIRES — a model on a framework base (Model/Authenticatable/Pivot) whose table
is declared by a migration, where a `json`/`boolean`/non-timestamp-datetime/`decimal`
column has no `$casts`/`casts()`/`$dates`/accessor entry.

WHAT DOES NOT — created_at/updated_at/deleted_at (Eloquent auto-casts); a column
already cast/dated/accessor-backed; a model whose table is not in the scanned
migrations; a model extending a custom base (casts may be inherited). Advisory (a
WARNING); not auto-fixable.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $index = MigrationSchemaIndex::forFile($filePath);

        if ($index->isEmpty()) {
            return $this->righteous();
        }

        $finder = new NodeFinder;
        $warnings = [];

        foreach ($finder->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            if ($class->isAbstract() || ! $this->extendsFrameworkModel($class)) {
                continue;
            }

            $table = $this->resolveTable($class);

            if ($table === null || ! $index->hasTable($table)) {
                continue; // unknown table → can't verify
            }

            $handled = $this->handledColumns($class, $finder);

            foreach ($index->columnsOf($table) as $column => $type) {
                if (! in_array($type, self::TYPED, true)
                    || in_array($column, self::AUTO_CAST, true)
                    || isset($handled[$column])
                ) {
                    continue;
                }

                $warnings[] = $this->warningAt(
                    $class->getStartLine(),
                    sprintf(
                        "The `%s` table declares column `%s` as %s (in its migration), but model `%s` does not cast it — so `$%s` is read back as a raw string, not the %s the schema promises. Add it to casts(): `'%s' => '%s'`.",
                        $table,
                        $column,
                        $type,
                        $class->name?->toString() ?? 'this model',
                        $column,
                        $this->describe($type),
                        $column,
                        $this->castFor($type),
                    ),
                    null,
                    'migration-model-drift:' . $table . '.' . $column,
                );
            }
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    private function extendsFrameworkModel(Node\Stmt\Class_ $class): bool
    {
        return $class->extends instanceof Node\Name
            && in_array(strtolower($class->extends->getLast()), self::MODEL_BASES, true);
    }

    /** The model's table: explicit `$table`, else the studly-plural-snake convention. */
    private function resolveTable(Node\Stmt\Class_ $class): ?string
    {
        foreach ($class->getProperties() as $prop) {
            if ($prop->props[0]->name->toString() === 'table'
                && $prop->props[0]->default instanceof Node\Scalar\String_
            ) {
                return strtolower($prop->props[0]->default->value);
            }
        }

        $name = $class->name?->toString();

        return $name === null ? null : $this->conventionTable($name);
    }

    /** Laravel's default: snake_case of the pluralised studly class name. */
    private function conventionTable(string $studly): string
    {
        $plural = $this->pluralise($studly);
        $snake = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $plural));

        return $snake;
    }

    private function pluralise(string $word): string
    {
        if (preg_match('/[^aeiou]y$/i', $word)) {
            return substr($word, 0, -1) . 'ies';
        }

        if (preg_match('/(s|x|z|ch|sh)$/i', $word)) {
            return $word . 'es';
        }

        return $word . 's';
    }

    /**
     * Columns the model already handles: $casts / casts() / $dates keys, plus
     * accessor-backed columns (getXAttribute, and `name(): Attribute`).
     *
     * @return array<string, true>
     */
    private function handledColumns(Node\Stmt\Class_ $class, NodeFinder $finder): array
    {
        $handled = [];

        foreach ($class->getProperties() as $prop) {
            $propName = $prop->props[0]->name->toString();

            if ($propName === 'casts' && $prop->props[0]->default instanceof Node\Expr\Array_) {
                $this->addArrayKeys($prop->props[0]->default, $handled);
            }

            if ($propName === 'dates' && $prop->props[0]->default instanceof Node\Expr\Array_) {
                $this->addArrayValues($prop->props[0]->default, $handled);
            }
        }

        $casts = $class->getMethod('casts');

        if ($casts !== null) {
            foreach ($finder->findInstanceOf((array) $casts->stmts, Node\Stmt\Return_::class) as $ret) {
                if ($ret->expr instanceof Node\Expr\Array_) {
                    $this->addArrayKeys($ret->expr, $handled);
                }
            }
        }

        foreach ($class->getMethods() as $method) {
            $name = $method->name->toString();

            // Old-style accessor: getMetaDataAttribute() → meta_data
            if (preg_match('/^get(.+)Attribute$/', $name, $m)) {
                $handled[$this->snake($m[1])] = true;
            }

            // New-style accessor: protected function metaData(): Attribute
            if ($method->returnType instanceof Node\Name && $method->returnType->getLast() === 'Attribute') {
                $handled[$this->snake($name)] = true;
            }
        }

        return $handled;
    }

    /**
     * @param  array<string, true>  $into
     */
    private function addArrayKeys(Node\Expr\Array_ $array, array &$into): void
    {
        foreach ($array->items as $item) {
            if ($item instanceof Node\Expr\ArrayItem && $item->key instanceof Node\Scalar\String_) {
                $into[strtolower($item->key->value)] = true;
            }
        }
    }

    /**
     * @param  array<string, true>  $into
     */
    private function addArrayValues(Node\Expr\Array_ $array, array &$into): void
    {
        foreach ($array->items as $item) {
            if ($item instanceof Node\Expr\ArrayItem && $item->value instanceof Node\Scalar\String_) {
                $into[strtolower($item->value->value)] = true;
            }
        }
    }

    private function snake(string $studly): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $studly));
    }

    private function describe(string $type): string
    {
        return match ($type) {
            'json' => 'array/object', 'bool' => 'boolean', 'datetime' => 'Carbon date', 'decimal' => 'decimal', default => $type,
        };
    }

    private function castFor(string $type): string
    {
        return match ($type) {
            'json' => 'array', 'bool' => 'boolean', 'datetime' => 'datetime', 'decimal' => 'decimal:2', default => $type,
        };
    }
}
