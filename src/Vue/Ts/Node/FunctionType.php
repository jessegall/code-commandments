<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Ts\Node;

/**
 * A function type — `(a: T, b?: U) => R`, `() => void`, curried `(a) => (b) => R`. A first-class
 * production, so the arrow is NEVER mistaken for an initializer and the type never truncates (the
 * bug that started this rewrite). References union the params' and the return's.
 */
final class FunctionType extends TypeNode
{
    /**
     * @param  list<Param>  $params
     */
    public function __construct(
        public readonly array $params,
        public readonly TypeNode $returnType,
    ) {}

    public function render(): string
    {
        return '(' . implode(', ', array_map(static fn (Param $p): string => $p->render(), $this->params)) . ') => ' . $this->returnType->render();
    }

    public function references(): array
    {
        $names = $this->returnType->references();

        foreach ($this->params as $param) {
            $names = [...$names, ...$param->references()];
        }

        return $names;
    }
}
