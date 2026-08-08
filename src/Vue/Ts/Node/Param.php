<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Ts\Node;

/**
 * One parameter of a function type or signature — `name`, `name?`, `...name`, each optionally
 * `: Type`. A parameter with no annotation renders as just its name (as authored).
 */
final class Param extends Node
{
    public function __construct(
        public readonly string $name,
        public readonly ?TypeNode $type = null,
        public readonly bool $optional = false,
        public readonly bool $rest = false,
    ) {}

    public function declaredNames(): array
    {
        return [$this->name];
    }

    /**
     * Might this argument be MISSING — written `name?: T`, or typed to admit `null`/`undefined`?
     * The same question {@see FieldDecl::isOptional} answers, asked of a parameter.
     */
    public function isOptional(): bool
    {
        return $this->optional || ($this->type?->admitsAbsence() ?? false);
    }

    public function render(): string
    {
        $prefix = $this->rest ? '...' : '';
        $suffix = $this->optional ? '?' : '';

        return $prefix . $this->name . $suffix . ($this->type !== null ? ': ' . $this->type->render() : '');
    }

    /**
     * @return list<string>
     */
    public function references(): array
    {
        return $this->type?->references() ?? [];
    }
}
