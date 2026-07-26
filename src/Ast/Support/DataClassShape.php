<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

use JesseGall\CodeCommandments\Support\ClassName;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\ClassField;
use JesseGall\CodeCommandments\Ast\Codebase;
use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\UnionType;
use PhpParser\NodeFinder;

/**
 * Determines whether a Spatie `Data` class is rich — has casts, maps, nested hydration,
 * or factories that `::from()` executes but `new` skips. Richness is inherited.
 */
final class DataClassShape
{
    use MemoisedPerCodebase;

    /** The Spatie `Data` base — the FQCN this shape analyser tests fields and elements against. */
    private const string DATA = 'Spatie\\LaravelData\\Data';

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
     * Attributes that REMAP the input key a property is hydrated from — so `::from()`
     * expects the mapped name, not the PHP property name. A `new Foo(prop: …)` → `Foo::from(
     * ['prop' => …])` rewrite is only sound when NONE of these are in play (a value cast like
     * `WithCast` keeps the key, so it stays safe).
     */
    private const array NAME_MAPPING_ATTRIBUTES = [
        'MapInputName',
        'MapName',
    ];

    /**
     * @param  array<string, Class_>  $classes  FQCN => declaration
     */
    private function __construct(private readonly array $classes) {}

    /**
     * The class declaration for $fqcn if it lives in the scanned codebase, else null —
     * a fixer that can't see the declaration can't prove its rewrite safe.
     */
    public function classFor(?string $fqcn): ?Class_
    {
        return $fqcn === null ? null : ($this->classes[ltrim($fqcn, '\\')] ?? null);
    }

    /**
     * Does the named class (or an ancestor) REMAP any input name — a class-level mapper or a
     * `MapInputName`/`MapName` on any property or promoted param? If so, `::from()` keys by the
     * mapped name, so a property-name-keyed rewrite would silently mismap; the fixer must skip.
     *
     * @param  array<string, true>  $seen  cycle guard across the parent walk
     */
    public function remapsInputNames(?string $fqcn, array $seen = []): bool
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

        if ($this->hasMappingAttribute($class->attrGroups)) {
            return true;
        }

        foreach ($class->getProperties() as $property) {
            if ($this->hasMappingAttribute($property->attrGroups)) {
                return true;
            }
        }

        foreach (AstNode::constructorParamsOf($class) as $param) {
            if ($this->hasMappingAttribute($param->attrGroups)) {
                return true;
            }
        }

        return $class->extends instanceof Name && $this->remapsInputNames($class->extends->toString(), $seen);
    }

    protected static function build(Codebase $codebase): static
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

        $constructor = AstNode::constructorOf($class);

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
        return $this->hasAttribute($groups, self::RICH_ATTRIBUTES);
    }

    /**
     * @param  list<AttributeGroup>  $groups
     */
    private function hasMappingAttribute(array $groups): bool
    {
        return $this->hasAttribute($groups, self::NAME_MAPPING_ATTRIBUTES);
    }

    /**
     * @param  list<AttributeGroup>  $groups
     * @param  list<string>  $names
     */
    private function hasAttribute(array $groups, array $names): bool
    {
        foreach ($groups as $group) {
            foreach ($group->attrs as $attribute) {
                if (in_array(ClassName::short($attribute->name->toString()), $names, true)) {
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
        $short = ClassName::short($name);

        return str_contains($short, 'DataCollection')
            || $short === 'Optional'
            || $short === 'Lazy'
            || $codebase->extends($name, self::DATA);
    }

    /**
     * Does this class COMPOSE more than one nested `Data` — at least two fields whose type is itself a
     * `Data` subclass, or a typed collection of one (`#[DataCollectionOf(SomeData::class)]`)? That is the
     * shape of a page object: a payload assembled from smaller Data slots, not a leaf DTO. A single
     * nested Data (a thin wrapper) is below the bar and does NOT qualify.
     */
    public function composesMultipleData(?string $fqcn, Codebase $codebase): bool
    {
        $count = 0;

        foreach ($codebase->classNamed($fqcn)->fields() as $field) {
            if ($this->fieldHoldsData($field, $codebase)) {
                $count++;
            }
        }

        return $count >= 2;
    }

    /**
     * Does this field hold nested `Data` — either its declared type names a `Data` subclass (directly or
     * within a `?T`/union/intersection), or it is a typed collection whose element is `Data`?
     */
    private function fieldHoldsData(ClassField $field, Codebase $codebase): bool
    {
        return $this->typeNamesData($field->type, $codebase)
            || $this->collectionElementIsData($field, $codebase);
    }

    /**
     * Does this type node name a `Data` subclass — unwrapping `?T` and scanning the members of a
     * union/intersection (so `WarehouseData|Lazy` counts)?
     */
    private function typeNamesData(?Node $type, Codebase $codebase): bool
    {
        if ($type instanceof NullableType) {
            return $this->typeNamesData($type->type, $codebase);
        }

        if ($type instanceof UnionType || $type instanceof IntersectionType) {
            foreach ($type->types as $inner) {
                if ($this->typeNamesData($inner, $codebase)) {
                    return true;
                }
            }

            return false;
        }

        return $type instanceof Name && $codebase->extends($type->toString(), self::DATA);
    }

    /**
     * Is this field a typed collection of `Data` — a `#[DataCollectionOf(X::class)]` whose element X is
     * itself a `Data` subclass?
     */
    private function collectionElementIsData(ClassField $field, Codebase $codebase): bool
    {
        foreach ($field->attributeGroups as $group) {
            foreach ($group->attrs as $attribute) {
                if (ClassName::short($attribute->name->toString()) !== 'DataCollectionOf') {
                    continue;
                }

                $element = self::attributeClassArgument($attribute);

                if ($element !== null && $codebase->extends($element, self::DATA)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The class named by an attribute's first argument — `#[X(SomeData::class)]` or `#[X('Some\Data')]`.
     */
    private static function attributeClassArgument(Attribute $attribute): ?string
    {
        $value = ($attribute->args[0] ?? null)?->value;

        if ($value instanceof ClassConstFetch && $value->class instanceof Name) {
            return $value->class->toString();
        }

        return $value instanceof String_ ? $value->value : null;
    }
}
