<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Ts\Node;

/**
 * A built-in type keyword — `string`, `number`, `boolean`, `void`, `unknown`, `never`, `any`,
 * `null`, `undefined`, `object`, `symbol`, `bigint`, `true`, `false`. References nothing.
 */
final class KeywordType extends TypeNode
{
    public function __construct(public readonly string $name) {}

    public function render(): string
    {
        return $this->name;
    }
}
