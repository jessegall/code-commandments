<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Packages\Tags;

use JesseGall\CodeCommandments\Packages\Exemption;

/**
 * Exemption tag: a framework CONFIG class whose whole job is handing the framework arrays (a
 * `FormRequest`, an MCP tool). Read by array-return-bag class-level, so it's robust to framework
 * hooks a rule can't enumerate.
 */
final class ArrayReturning extends Exemption
{
    public function slug(): string
    {
        return 'array-returning';
    }

    public function description(): string
    {
        return 'A class whose whole job is handing the framework arrays (a FormRequest, an MCP tool) — its array returns are contractual, exempt from array-return-bag (class-level).';
    }
}
