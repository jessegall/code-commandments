<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Ts\Node;

/**
 * The `name => rendered type` view of a members-bearing type — shared by {@see InterfaceDecl} and
 * {@see ObjectType}, the two shapes the prop-typing consumers read uniformly. One home for the fold over
 * `$members`. Requires the using class to hold a `$members` list of {@see Member}.
 */
trait MembersAsFields
{
    /**
     * @return array<string, string>
     */
    public function fields(): array
    {
        $fields = [];

        foreach ($this->members as $member) {
            $fields[$member->name] = $member->type()->render();
        }

        return $fields;
    }
}
