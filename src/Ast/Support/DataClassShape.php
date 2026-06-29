<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

use JesseGall\CodeCommandments\Ast\Codebase;
use PhpParser\Node;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\UnionType;
use PhpParser\NodeFinder;

/**
 * Decides whether a Spatie `Data` class is RICH — i.e. whether routing its
 * construction through `::from()` actually DOES something a raw `new` would skip:
 * a cast, a name map, a nested-Data hydration, or a magic `fromX()` factory.
 *
 * A PLAIN Data class — only scalar/enum promoted props, no cast/map/nest/factory —
 * gains nothing from `::from()` over `new`, so `new` is honest there. The smell is
 * only `new` on a rich class, where it silently bypasses the framework pipeline.
 *
 * Richness is inherited: a subclass of a rich base is rich. Resolution walks the
 * codebase's own class declarations — no reflection, no runtime.
 */
final class DataClassShape
{
    /**
     * Attributes that make construction non-trivial — a cast, transform, name map,
     * or typed-collection hydration that `::from()` runs and `new` bypasses.
     */
    private const array RICH_ATTRIBUTES = [
        'WithCast',
        'WithCastable',
        'WithTransformer',
        'MapInputName',
        'MapName',
        'DataCollectionOf',
    ];

    /**
     * @param  array<string, Class_>  $classes  FQCN => declaration
     */
    private function __construct(private readonly array $classes) {}

    public static function forCodebase(Codebase $codebase): self
    {
        $classes = [];
        $finder = new NodeFinder;

        foreach ($codebase->files() as $file) {
            foreach ($finder->findInstanceOf($file->ast, Class_::class) as $class) {
                $name = ($class->namespacedName ?? null)?->toString();

                if ($name !== null) {
                    $classes[ltrim($name, '\\')] = $class;
                }
            }
        }

        return new self($classes);
    }

    /**
     * Is the named Data class rich — does `::from()` do work `new` would skip?
     *
     * @param  array<string, true>  $seen  cycle guard across the parent walk
     */
    public function isRich(?string $fqcn, Codebase $codebase, array $seen = []): bool
    {
        if ($fqcn === null) {
            return false;
        }

        $fqcn = ltrim($fqcn, '\\');
        $class = $this->classes[$fqcn] ?? null;

        if ($class === null || isset($seen[$fqcn])) {
            return false;
        }

        $seen[$fqcn] = true;

        if ($this->hasRichAttribute($class->attrGroups)) {
            return true;
        }

        foreach ($class->getMethods() as $method) {
            $name = $method->name->toString();

            if ($name === 'casts' || preg_match('/^from[A-Z]/', $name) === 1) {
                return true;
            }
        }

        $constructor = $class->getMethod('__construct');

        if ($constructor !== null) {
            foreach ($constructor->params as $param) {
                if ($param->flags === 0) {
                    continue; // not a promoted property — irrelevant to the Data shape
                }

                if ($this->hasRichAttribute($param->attrGroups) || $this->isCastableType($param->type, $codebase)) {
                    return true;
                }
            }
        }

        return $class->extends instanceof Name
            && $this->isRich($class->extends->toString(), $codebase, $seen);
    }

    /**
     * @param  list<AttributeGroup>  $groups
     */
    private function hasRichAttribute(array $groups): bool
    {
        foreach ($groups as $group) {
            foreach ($group->attrs as $attribute) {
                if (in_array(self::shortName($attribute->name->toString()), self::RICH_ATTRIBUTES, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * A property type that `::from()` would hydrate beyond a plain assignment — a
     * nested Data object, or a Data collection / optional wrapper.
     */
    private function isCastableType(?Node $type, Codebase $codebase): bool
    {
        if ($type instanceof NullableType) {
            return $this->isCastableType($type->type, $codebase);
        }

        if ($type instanceof UnionType || $type instanceof IntersectionType) {
            foreach ($type->types as $inner) {
                if ($this->isCastableType($inner, $codebase)) {
                    return true;
                }
            }

            return false;
        }

        if (! $type instanceof Name && ! $type instanceof Identifier) {
            return false;
        }

        $name = $type->toString();
        $short = self::shortName($name);

        return str_contains($short, 'DataCollection')
            || $short === 'Optional'
            || $short === 'Lazy'
            || $codebase->extends($name, 'Spatie\\LaravelData\\Data');
    }

    private static function shortName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts);
    }
}
