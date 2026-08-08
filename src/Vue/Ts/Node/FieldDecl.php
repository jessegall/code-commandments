<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Ts\Node;

use JesseGall\CodeCommandments\Vue\Expr\Expr;
use JesseGall\CodeCommandments\Vue\Ts\Modifiers;

/**
 * A field declared in a class body — `private readonly items: Item[] = []`. The class-body sibling
 * of {@see Property}, which is the same idea inside an object type.
 */
final class FieldDecl extends Node
{
    public function __construct(
        public readonly string $name,
        public readonly ?TypeNode $type = null,
        public readonly Modifiers $modifiers = new Modifiers(),
        public readonly ?Expr $initializer = null,
        public readonly bool $optional = false,
    ) {}

    public function declaredNames(): array
    {
        return [$this->name];
    }

    /**
     * Might this field be MISSING — written `name?: T`, or typed to admit `null`/`undefined`? Both
     * spellings mean the same thing to an absence rule, so both are one question here.
     */
    public function isOptional(): bool
    {
        return $this->optional || ($this->type?->admitsAbsence() ?? false);
    }

    public function render(): string
    {
        $type = $this->type !== null ? ': ' . $this->type->render() : '';
        $init = $this->initializer !== null ? ' = ' . $this->initializer->source() : '';

        return $this->modifiers->render() . $this->name . ($this->optional ? '?' : '') . $type . $init . ';';
    }
}
