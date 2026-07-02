<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

use JesseGall\CodeCommandments\Located;

/**
 * A matched {@see TypeDeclaration} that knows WHERE it is — the {@see ElementMatch} of
 * declaration space, so a frontend detector over types reads `file:line` and `scope`
 * exactly like one over elements. A `where`/`reject` predicate receives this and asks
 * it for the declaration's {@see name} and {@see fields}.
 *
 * NOT final, mirroring {@see ElementMatch}: a detector may subclass it to hang domain
 * predicates on a declaration and type-hint the subclass in a `where` closure.
 */
class TypeDeclarationMatch implements Located
{
    public function __construct(public readonly TypeDeclaration $declaration) {}

    public function name(): string
    {
        return $this->declaration->name;
    }

    /**
     * @return list<string>
     */
    public function fields(): array
    {
        return $this->declaration->fields;
    }

    public function fieldCount(): int
    {
        return count($this->declaration->fields);
    }

    public function file(): string
    {
        return $this->declaration->file;
    }

    public function location(): string
    {
        return $this->declaration->file . ':' . $this->declaration->line;
    }

    /**
     * A short context for the report — the type this sin sits on.
     */
    public function scope(): string
    {
        return "type {$this->declaration->name}";
    }
}
