<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Judgment;

/**
 * Commandment: Data properties - readonly is optional.
 *
 * This rule is disabled - we don't enforce readonly on constructor properties.
 */
class ReadonlyDataPropertiesProphet extends PhpCommandment
{
    public function description(): string
    {
        return 'Data class properties should not use readonly outside constructor';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
In PHP, readonly can only be used on constructor-promoted properties.
Body-declared properties cannot have the readonly modifier.

This rule is informational only - it does not enforce adding readonly
to constructor properties.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        // This rule is disabled - we don't enforce readonly on constructor properties
        // and body-declared readonly properties are a PHP syntax error anyway
        return $this->righteous();
    }
}
