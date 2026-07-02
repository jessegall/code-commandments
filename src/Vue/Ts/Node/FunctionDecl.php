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
     * @param  ?array<string, ?string>  $returnObject  a `return { a, b: c }` shape — field => the
     *   local it returns (null for a non-name value) — so an INFERRED return type can still be
     *   resolved field-by-field from the composable's own declarations.
     */
    public function __construct(
        public readonly string $name,
        public readonly array $params,
        public readonly ?TypeNode $returnType = null,
        public readonly ?array $returnObject = null,
        public readonly string $bodySource = '',
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
