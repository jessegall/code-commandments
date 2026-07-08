<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Ts\Node;

/**
 * The `references()` of a type that is a bag of members — {@see CompositeType} (union/intersection members),
 * {@see InterfaceDecl} and {@see ObjectType} (declared members): the named types it depends on are the union
 * of every member's own references. One home so the three aggregate types don't each re-spell the fold.
 * Requires the using class to hold a `$members` list whose elements each expose `references(): list<string>`.
 */
trait AggregatesMemberReferences
{
    /**
     * @return list<string>
     */
    public function references(): array
    {
        $names = [];

        foreach ($this->members as $member) {
            $names = [...$names, ...$member->references()];
        }

        return $names;
    }
}
