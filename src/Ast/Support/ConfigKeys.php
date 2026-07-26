<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

use JesseGall\CodeCommandments\Ast\Codebase;
use PhpParser\Node;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Scalar\Encapsed;
use PhpParser\Node\Scalar\EncapsedStringPart;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;

/**
 * Which keys a project's `config/*.php` files DECLARE, and which of them nothing reads. A key is read
 * by any string literal that names it, contains it, or is contained by it — reading a parent pulls the
 * whole subtree, so the test is deliberately generous in both directions.
 */
final class ConfigKeys
{
    use MemoisedPerCodebase;

    /**
     * @var array<string, bool> project root + config name => is it a vendor-published file
     */
    private static array $published = [];

    /**
     * @param  array<int, string>   $declared  ArrayItem object-id => its dotted key
     * @param  list<string>         $literals  every string literal in the codebase that could name a key
     * @param  array<string, true>  $files     config file prefixes something in the tree actually reads
     */
    private function __construct(
        private readonly array $declared,
        private readonly array $literals,
        private readonly array $files,
        private readonly array $defaults = [],
    ) {}

    /**
     * Does the config FILE already state a default for this key — a literal value, or an
     * `env('VAR', <fallback>)` carrying one? If so, a reader supplying its own fallback states the
     * same decision a second time, and the two drift the moment either is edited.
     */
    public function declaresDefault(string $key): bool
    {
        return isset($this->defaults[$key]);
    }

    /**
     * The dotted key this node declares if NOTHING reads it, else null. Null too for a key whose whole
     * config file is unread here — that file belongs to a scope this scan can't see, so its keys say
     * nothing about the project.
     */
    public function deadKeyAt(Node $node): ?string
    {
        $key = $this->declared[spl_object_id($node)] ?? null;

        if ($key === null || ! isset($this->files[explode('.', $key)[0]])) {
            return null;
        }

        return $this->isRead($key) ? null : $key;
    }

    private function isRead(string $key): bool
    {
        return array_any(
            $this->literals,
            static fn (string $literal): bool => $literal === $key
                || str_starts_with($literal, $key . '.')
                || str_starts_with($key, $literal . '.'),
        );
    }

    protected static function build(Codebase $codebase): static
    {
        $declared = [];
        $defaults = [];
        $literals = [];
        $finder = new NodeFinder;

        foreach ($codebase->files() as $file) {
            foreach ($finder->findInstanceOf($file->ast, String_::class) as $string) {
                $literals[] = $string->value;
            }

            // An interpolated read (`"workflows.generators.namespaces.{$type}"`) still names its
            // leading segments, and that head is what keeps the subtree alive.
            foreach ($finder->findInstanceOf($file->ast, Encapsed::class) as $encapsed) {
                if (($encapsed->parts[0] ?? null) instanceof EncapsedStringPart) {
                    $literals[] = $encapsed->parts[0]->value;
                }
            }

            $prefix = self::configPrefixOf($file->path);

            if ($prefix !== null) {
                self::collect(self::returnedArray($file->ast), $prefix, $declared, $defaults);
            }
        }

        $files = [];

        foreach ($declared as $key) {
            $file = explode('.', $key)[0];

            if (! isset($files[$file]) && array_any($literals, static fn (string $l): bool => str_starts_with($l, $file . '.'))) {
                $files[$file] = true;
            }
        }

        return new self($declared, $literals, $files, $defaults);
    }

    /**
     * The config namespace a file contributes — its basename, when it sits in a `config` directory
     * AND the project OWNS it. Null for anything else, so an ordinary array-returning file is never
     * mistaken for config.
     *
     * Ownership is the crux. A PUBLISHED config (`services.php` from the framework,
     * `event-sourcing.php` from a package) is read by the vendor code that published it, which the
     * scan never sees — so its keys always look unread. If any installed package ships a config file
     * of the same name, this one is a published copy and is left alone.
     */
    private static function configPrefixOf(string $path): ?string
    {
        if (basename(dirname($path)) !== 'config') {
            return null;
        }

        $name = basename($path, '.php');

        return self::isPublishedByAPackage(dirname($path, 2), $name) ? null : $name;
    }

    private static function isPublishedByAPackage(string $root, string $name): bool
    {
        return self::$published[$root . '/' . $name] ??= glob($root . '/vendor/*/*/config/' . $name . '.php') !== []
            || glob($root . '/vendor/*/*/src/config/' . $name . '.php') !== [];
    }

    /**
     * @param  array<int, Node>  $ast
     */
    private static function returnedArray(array $ast): ?Array_
    {
        foreach ($ast as $statement) {
            if ($statement instanceof Return_ && $statement->expr instanceof Array_) {
                return $statement->expr;
            }
        }

        return null;
    }

    /**
     * Walk a config array, recording the dotted key of every LEAF (a non-array value). A nested array
     * is a namespace, not a setting — deleting it means deleting the leaves under it.
     *
     * @param  array<int, string>   $declared
     * @param  array<string, true>  $defaults
     */
    private static function collect(?Array_ $array, string $prefix, array &$declared, array &$defaults): void
    {
        foreach ($array?->items ?? [] as $item) {
            if (! $item instanceof ArrayItem || ! $item->key instanceof String_) {
                continue;
            }

            $key = $prefix . '.' . $item->key->value;

            if ($item->value instanceof Array_) {
                self::collect($item->value, $key, $declared, $defaults);

                continue;
            }

            $declared[spl_object_id($item)] = $key;

            if (self::statesADefault($item->value)) {
                $defaults[$key] = true;
            }
        }
    }

    /**
     * Does this declared VALUE carry a default? A scalar literal is one outright; `env('VAR', 8086)`
     * is one too. A bare `env('VAR')` states no fallback, so a reader supplying one is the only
     * source of truth and nothing is duplicated.
     */
    private static function statesADefault(Node $value): bool
    {
        if ($value instanceof String_ || $value instanceof Int_ || $value instanceof Float_) {
            return true;
        }

        if ($value instanceof ConstFetch) {
            return in_array(strtolower($value->name->toString()), ['true', 'false'], true);
        }

        return $value instanceof FuncCall
            && $value->name instanceof Name
            && $value->name->toString() === 'env'
            && count($value->args) >= 2;
    }
}
