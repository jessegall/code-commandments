<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Ts\Node;

/**
 * A method signature — `m(a: T): R`, `m?(): void`. As a member it renders in method form; its
 * {@see type} is the equivalent `(a: T) => R` function type, so a `defineProps` method member reads
 * as a callable prop like any other.
 */
final class Method extends Member
{
    /**
     * @param  list<Param>  $params
     */
    public function __construct(
        string $name,
        private readonly array $params,
        private readonly TypeNode $returnType,
        bool $optional = false,
    ) {
        parent::__construct($name, $optional);
    }

    public function type(): TypeNode
    {
        return new FunctionType($this->params, $this->returnType);
    }

    public function render(): string
    {
        $params = implode(', ', array_map(static fn (Param $p): string => $p->render(), $this->params));

        return $this->name . ($this->optional ? '?' : '') . "({$params}): " . $this->returnType->render();
    }
}
