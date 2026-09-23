<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py\Node;

/**
 * An `import a.b as c` or a `from ..a import b as c`. $names maps each imported name to the name it is
 * bound under; $module and $level are the `from` part — the dotted module and how many dots precede it.
 */
final class Import extends Node
{
    /**
     * @param  array<string, string>  $names
     */
    public function __construct(
        public readonly array $names,
        public readonly ?string $module = null,
        public readonly int $level = 0,
    ) {}

    public function variant(): string
    {
        return $this->module === null ? 'import' : 'from';
    }

    public function declaredNames(): array
    {
        return array_values($this->names);
    }

    /**
     * The dotted name each name this binds stands for — `j` → `json` for `import json as j`, `os` → `os` for
     * `import os.path`, `r` → `os.rename` for `from os import rename as r`. A relative import names a module
     * of the project's own, and binds nothing here.
     *
     * @return array<string, string>
     */
    public function dottedBindings(): array
    {
        if ($this->level > 0) {
            return [];
        }

        $bound = [];

        foreach ($this->names as $imported => $alias) {
            $bound[$alias] = match (true) {
                $this->module !== null => "{$this->module}.{$imported}",
                $alias === explode('.', $imported)[0] => $alias,
                default => $imported,
            };
        }

        return $bound;
    }
}
