<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

use JesseGall\CodeCommandments\Ast\NodeMatch;

/**
 * One unit of code and what it reaches — the pairing every reach-comparing rule works in terms of, so
 * the declaration a finding is reported at travels with the resources the verdict is drawn from.
 */
final class ReachedUnit
{
    /**
     * @param  list<string>  $resources  what this unit reaches, rarest first
     */
    public function __construct(
        public readonly NodeMatch $match,
        public readonly array $resources,
    ) {}

    public function count(): int
    {
        return count($this->resources);
    }

    /**
     * What $other reaches that this does not.
     *
     * @return list<string>
     */
    public function missingFrom(self $other): array
    {
        return array_values(array_diff($other->resources, $this->resources));
    }

    /**
     * What both reach.
     *
     * @return list<string>
     */
    public function sharedWith(self $other): array
    {
        return array_values(array_intersect($this->resources, $other->resources));
    }
}
