<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

/**
 * A TypeScript object type declared in the frontend — an `interface`/`type` with its
 * field names, and where it sits (`file:line`). The declaration-space twin of an
 * {@see Element}: what the {@see TypeQuery} draws from, so a detector reasons over the
 * types a codebase declares the same way it reasons over the elements it renders.
 *
 * It reads itself off a {@see Script} ({@see fromScript}) — the ONE place the engine
 * turns lexed type declarations into located values, whether the script is a `.vue`
 * block or a standalone `.ts` file.
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
            $line = substr_count($source, "\n", 0, $baseOffset + $declaration['offset']) + 1;

            $declarations[] = new self($declaration['name'], $declaration['fields'], $file, $line);
        }

        return $declarations;
    }
}
