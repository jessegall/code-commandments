<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

use JesseGall\CodeCommandments\Ast\ParsedFile;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\UseItem;
use PhpParser\NodeFinder;

/**
 * The one home for reading a TYPE out of a docblock — the shape PHP itself cannot declare: the
 * ELEMENT type of a collection (`@var list<Payment>`, `Payment[]`, `Collection<int, Payment>`), with
 * the name resolved through the file's own imports and namespace so it answers with the FQCN the code
 * would. The language types the container and stops there, so without this a walk over
 * `foreach ($order->payments as $payment)` loses the receiver (#449). The one home for reading a
 * docblock's TYPES, as {@see Docblock} is for its shape.
 */
final class DocType
{
    /**
     * Type names that are never a class — a collection of these carries no receiver worth resolving.
     */
    private const array SCALARS = [
        'string', 'int', 'integer', 'float', 'double', 'bool', 'boolean', 'array', 'iterable',
        'object', 'mixed', 'callable', 'null', 'void', 'never', 'true', 'false', 'scalar', 'resource',
        'key-of', 'value-of', 'non-empty-string', 'positive-int', 'negative-int', 'array-key',
    ];

    /**
     * The element type $docblock declares for $tag — `list<Payment>`, `Payment[]`,
     * `array<string, Payment>` and `Collection<int, Payment>` all yield `Payment`, as WRITTEN.
     * Null when the block names no collection, or one of scalars.
     *
     * @param  string|null  $variable  the `$name` a `@param`/`@var` must be about, when it names one
     */
    public static function elementNamed(?string $docblock, ?string $variable = null): ?string
    {
        if ($docblock === null) {
            return null;
        }

        foreach (self::declaredTypes($docblock, $variable) as $type) {
            $element = self::elementOf($type);

            if ($element !== null) {
                return $element;
            }
        }

        return null;
    }

    /**
     * The element of ONE written type expression: the last type argument of its outermost generic
     * (`array<string, Payment>` → `Payment`), or the item type of a `Payment[]`. Null when the
     * expression is not a collection of a class.
     */
    public static function elementOf(string $type): ?string
    {
        $open = strpos($type, '<');

        if ($open !== false && str_ends_with($type, '>')) {
            $arguments = explode(',', substr($type, $open + 1, -1));
            $type = trim((string) end($arguments));
        } elseif (str_ends_with($type, '[]')) {
            $type = substr($type, 0, -2);
        } else {
            return null; // Not a collection: a scalar or a plain class carries no element.
        }

        $type = ltrim(trim($type), '\\');

        return $type !== '' && ! in_array(strtolower($type), self::SCALARS, true) ? $type : null;
    }

    /**
     * A name as WRITTEN in a docblock, resolved the way the file's code would resolve it: through an
     * import (`use App\Payment;` — or its alias), else against the file's own namespace. An already
     * fully-qualified name is returned as it stands.
     */
    public static function resolve(string $written, ParsedFile $file): string
    {
        if (str_starts_with($written, '\\')) {
            return ltrim($written, '\\'); // Already absolute — neither an import nor the namespace applies.
        }

        $root = explode('\\', $written)[0];
        $imports = self::importsIn($file);

        if (isset($imports[$root])) {
            $tail = substr($written, strlen($root));

            return $imports[$root] . $tail;
        }

        $namespace = self::namespaceOf($file);

        return $namespace === '' ? $written : "{$namespace}\\{$written}";
    }

    /**
     * The file's class imports, keyed by the local name each is known by (its alias when aliased).
     *
     * @return array<string, string>
     */
    public static function importsIn(ParsedFile $file): array
    {
        $imports = [];

        foreach (new NodeFinder()->findInstanceOf($file->ast, Use_::class) as $use) {
            if ($use->type === Use_::TYPE_NORMAL) {
                $imports += self::mapped($use->uses);
            }
        }

        foreach (new NodeFinder()->findInstanceOf($file->ast, GroupUse::class) as $group) {
            $prefix = $group->prefix->toString();

            foreach (self::mapped($group->uses) as $local => $target) {
                $imports[$local] = "{$prefix}\\{$target}";
            }
        }

        return $imports;
    }

    /**
     * @param  list<UseItem>  $uses
     * @return array<string, string>
     */
    private static function mapped(array $uses): array
    {
        $imports = [];

        foreach ($uses as $item) {
            $target = $item->name->toString();
            $imports[$item->alias?->toString() ?? $item->name->getLast()] = $target;
        }

        return $imports;
    }

    private static function namespaceOf(ParsedFile $file): string
    {
        foreach (new NodeFinder()->findInstanceOf($file->ast, Namespace_::class) as $namespace) {
            return $namespace->name?->toString() ?? '';
        }

        return '';
    }

    /**
     * The types an `@var`/`@param` in $docblock declares, most specific first. When $variable is
     * given, a tag naming a DIFFERENT variable is skipped — a promoted constructor param is
     * documented alongside its siblings, so the block holds several.
     *
     * @return list<string>
     */
    private static function declaredTypes(string $docblock, ?string $variable): array
    {
        preg_match_all('/@(?:var|param)\s+([^\s]+)(?:\s+\$([A-Za-z_]\w*))?/', $docblock, $matches, PREG_SET_ORDER);

        $types = [];

        foreach ($matches as $match) {
            $named = $match[2] ?? '';

            if ($variable === null || $named === '' || $named === $variable) {
                $types[] = $match[1];
            }
        }

        return $types;
    }
}
