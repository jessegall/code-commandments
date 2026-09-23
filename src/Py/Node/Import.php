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
}
