<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Packages\Tags;

use JesseGall\CodeCommandments\Packages\Exemption;

/**
 * Exemption tag: a call that DECLARES one end of a two-way association, naming the class at the
 * other end — an ORM's `hasOne(Picker::class)` answered by `belongsTo(User::class)`. The mapper
 * requires BOTH ends to name each other, so the reference carries no dependency direction: there is
 * no arrow to invert, and cutting either side deletes the relation. Exempt from the namespace-cycle
 * rule — the two really do point at each other, and that is the pattern working.
 */
final class Association extends Exemption
{
    public function slug(): string
    {
        return 'association';
    }

    public function description(): string
    {
        return 'A call declaring one end of a two-way association (an ORM relation like `hasOne(Picker::class)`) — the mapper requires both ends to name each other, so the reference carries no dependency direction and cannot close a cycle.';
    }
}
