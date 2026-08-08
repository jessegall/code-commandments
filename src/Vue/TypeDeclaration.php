<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

use JesseGall\CodeCommandments\Span;

/**
 * TypeScript object type (interface/type) with field names and file:line—declaration-space
 * twin of Element. Reads off Script via fromScript.
 */
final class TypeDeclaration
{
    /**
     * @param  list<string>  $fields
     */
    public function __construct(
        public readonly string $name,
        public readonly array $fields,
        public readonly string $file,
        public readonly int $line,
    ) {}

    /**
     * Every type $script declares, as located values. $source is the file the script
     * belongs to and $baseOffset where the script body begins within it — so a `.vue`
     * script block (offset into the SFC) and a `.ts` file (offset 0) both map to a
     * correct file line.
     *
     * @return list<self>
     */
    public static function fromScript(Script $script, string $file, string $source, int $baseOffset = 0): array
    {
        $declarations = [];

        foreach ($script->declarations() as $declaration) {
            $line = Span::lineAt($source, $baseOffset + $declaration['offset']);

            $declarations[] = new self($declaration['name'], $declaration['fields'], $file, $line);
        }

        return $declarations;
    }
}
