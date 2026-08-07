<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

use JesseGall\CodeCommandments\Vue\Ts\Node\ImportDecl;

/**
 * One `import` a component's script carries — the names it binds, and the statement as it is
 * written, terminated, so a writer can splice it straight into a new `<script setup>`.
 */
final readonly class ImportStatement
{
    /**
     * @param  list<string>  $names
     */
    private function __construct(
        public array $names,
        public string $statement,
    ) {}

    public static function of(ImportDecl $import): self
    {
        $statement = $import->render();

        return new self(
            array_keys($import->bindings),
            str_ends_with($statement, ';') ? $statement : "{$statement};",
        );
    }

    /**
     * Does this import bind ANY name $matches accepts — the question behind "is this import still
     * needed by the code I am keeping?", asked of the import rather than of its name list.
     *
     * @param  callable(string): bool  $matches
     */
    public function bindsAny(callable $matches): bool
    {
        return array_any($this->names, $matches);
    }
}
