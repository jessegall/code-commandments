<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use PhpParser\Node;

/**
 * Detects Spatie Data attributes whose behavior a plain `from($x->toArray())`
 * round-trip cannot reproduce — `#[LoadRelation]` (relations loaded from the
 * model), `#[MapInputName]`/`#[MapName]` (input shape differs), `#[Computed]`
 * (excluded from input). A class carrying any of these depends on the magic
 * `from(Model)` path, so it can be neither auto-converted (ExplicitDataFactory)
 * nor made array-only (DataClassFromArrayOnly).
 */
final class SpatieDataMagic
{
    private const ATTRIBUTES = ['loadrelation', 'mapinputname', 'mapname', 'computed'];

    public static function classHasMagicAttribute(Node\Stmt\Class_ $class): bool
    {
        foreach ($class->getProperties() as $property) {
            if (self::groupsHaveMagic($property->attrGroups)) {
                return true;
            }
        }

        foreach ($class->getMethods() as $method) {
            if (self::groupsHaveMagic($method->attrGroups)) {
                return true;
            }

            if ($method->name->toString() === '__construct') {
                foreach ($method->params as $param) {
                    if (self::groupsHaveMagic($param->attrGroups)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param  array<Node\AttributeGroup>  $attrGroups
     */
    private static function groupsHaveMagic(array $attrGroups): bool
    {
        foreach ($attrGroups as $group) {
            foreach ($group->attrs as $attr) {
                if (in_array(strtolower($attr->name->getLast()), self::ATTRIBUTES, true)) {
                    return true;
                }
            }
        }

        return false;
    }
}
