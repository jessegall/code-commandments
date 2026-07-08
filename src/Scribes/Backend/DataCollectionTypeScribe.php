<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes\Backend;

use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Scribes\Draft;
use JesseGall\CodeCommandments\Scribes\RepentScribe;
use JesseGall\CodeCommandments\Scribes\Writer;
use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\UnionType;

/**
 * Retypes a `Data` property typed `DataCollection` to `array` — but ONLY when the element is already stated
 * on the AST via `#[DataCollectionOf(X::class)]`, so the collection stays element-typed after the change.
 * A property whose element lives only in a `@var` docblock is left for a hand-fix: the resolver reads the
 * element from the attribute (the AST), never by scraping docblock text.
 */
final class DataCollectionTypeScribe extends RepentScribe
{
    private const string DATA_COLLECTION = 'Spatie\\LaravelData\\DataCollection';

    public function rewrite(array $findings): array
    {
        $draft = $this->draft([]);

        foreach ($findings as $match) {
            if ($match instanceof NodeMatch && ($match->node instanceof Param || $match->node instanceof Property)) {
                $this->fix($draft, $match);
            }
        }

        return $draft->rewrites();
    }

    private function fix(Draft $draft, NodeMatch $match): void
    {
        $node = $match->node;
        $typeName = self::dataCollectionNameIn($node->type);

        // Only rewrite when `#[DataCollectionOf]` already names the element — else retyping to `array`
        // would drop the element typing, so leave it for a hand-fix.
        if ($typeName === null || ! self::carriesDataCollectionOf($node)) {
            return;
        }

        Writer::for($draft, $match)->replace($typeName, 'array');
    }

    /** The `DataCollection` Name node inside a bare / nullable / union type, or null. */
    private static function dataCollectionNameIn(?Node $type): ?Name
    {
        if ($type instanceof Name) {
            return ltrim($type->toString(), '\\') === self::DATA_COLLECTION ? $type : null;
        }

        if ($type instanceof NullableType) {
            return self::dataCollectionNameIn($type->type);
        }

        if ($type instanceof UnionType) {
            foreach ($type->types as $member) {
                if ($member instanceof Name && ltrim($member->toString(), '\\') === self::DATA_COLLECTION) {
                    return $member;
                }
            }
        }

        return null;
    }

    private static function carriesDataCollectionOf(Param|Property $node): bool
    {
        foreach ($node->attrGroups as $group) {
            foreach ($group->attrs as $attr) {
                $name = ltrim($attr->name->toString(), '\\');

                if ($name === 'DataCollectionOf' || str_ends_with($name, '\\DataCollectionOf')) {
                    return true;
                }
            }
        }

        return false;
    }
}
