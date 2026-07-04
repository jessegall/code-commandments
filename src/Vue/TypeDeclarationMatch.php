<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

use JesseGall\CodeCommandments\Located;

/**
 * A matched {@see TypeDeclaration} that knows WHERE it is — frontend detector's `file:line`
 * and `scope` like {@see ElementMatch}. Mirrors ElementMatch: non-final, allow subclassing
 * to hang domain predicates with type-hinted `where` closures.
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
