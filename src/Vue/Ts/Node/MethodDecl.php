<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Ts\Node;

use JesseGall\CodeCommandments\Vue\Ts\Modifiers;

/**
 * A method declared in a class body — `private async load(page: number): Promise<Page> { … }`, a
 * `constructor`, or a `get`/`set` accessor. The class-body sibling of {@see FunctionDecl}, and of
 * {@see Method}, which is the SIGNATURE form the same name takes inside an object type.
 */
final class MethodDecl extends Node
{
    /**
     * @param  list<Param>  $params
     */
    public function __construct(
        public readonly string $name,
        public readonly array $params,
        public readonly ?TypeNode $returnType = null,
        public readonly Modifiers $modifiers = new Modifiers(),
        public readonly ?BlockStmt $body = null,
        public readonly string $accessor = '',
    ) {}

    public function declaredNames(): array
    {
        return [$this->name];
    }

    public function children(): array
    {
        return array_values(array_filter([...$this->params, $this->body]));
    }

    public function isConstructor(): bool
    {
        return $this->name === 'constructor';
    }

    /**
     * Is this a `get`/`set` accessor rather than an ordinary method? An accessor is named like
     * STATE, so a rule about method mood must not judge it as a command.
     */
    public function isAccessor(): bool
    {
        return $this->accessor !== '';
    }

    public function signature(): FunctionType
    {
        return new FunctionType($this->params, $this->returnType ?? new KeywordType('void'));
    }

    public function render(): string
    {
        $params = implode(', ', array_map(static fn (Param $p): string => $p->render(), $this->params));
        $return = $this->returnType !== null ? ': ' . $this->returnType->render() : '';
        $accessor = $this->accessor !== '' ? $this->accessor . ' ' : '';

        return $this->modifiers->render() . $accessor . "{$this->name}({$params}){$return} " . ($this->body?->render() ?? '{}');
    }
}
