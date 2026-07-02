<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Ts\Node;

/**
 * A `function name(params): Return` declaration (its body is not modelled). Its {@see signature} is
 * the equivalent `(params) => Return` function type — how a function referenced as a callable prop
 * is typed — and {@see returnType} is its declared return, if any.
 */
final class FunctionDecl extends Node
{
    /**
     * @param  list<Param>  $params
     */
    public function __construct(
        public readonly string $name,
        public readonly array $params,
        public readonly ?TypeNode $returnType = null,
    ) {}

    public function signature(): FunctionType
    {
        return new FunctionType($this->params, $this->returnType ?? new KeywordType('void'));
    }

    public function render(): string
    {
        $params = implode(', ', array_map(static fn (Param $p): string => $p->render(), $this->params));
        $return = $this->returnType !== null ? ': ' . $this->returnType->render() : '';

        return "function {$this->name}({$params}){$return} {}";
    }
}
