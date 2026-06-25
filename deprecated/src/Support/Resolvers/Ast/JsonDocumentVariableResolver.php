<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Resolvers\Ast;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;

/**
 * Resolve whether a variable holds a deserialized JSON DOCUMENT that is edited in
 * place — assigned from `json_decode(...)`, or handed to `json_encode(...)` within
 * the same scope. Such a value is wire format (a composer.json, a package manifest,
 * an API payload), not a domain bag: subscripting it by key is the only way to edit
 * the document. Prophets that would otherwise push it toward a typed DTO
 * (NoArrayStringIndexing, NoArrayBag) consult this to stay silent on the round-trip.
 */
final class JsonDocumentVariableResolver
{
    private const DECODE = 'json_decode';

    private const ENCODE = 'json_encode';

    public function __construct(
        private readonly NodeFinder $nodeFinder = new NodeFinder(),
    ) {}

    /**
     * Whether `$variable`, within `$scope`, holds a JSON document round-trip.
     */
    public function isJsonDocument(Expr\Variable $variable, Node\FunctionLike $scope): bool
    {
        if (! is_string($variable->name) || $scope->getStmts() === null) {
            return false;
        }

        return $this->assignedFromDecode($variable->name, $scope)
            || $this->handedToEncode($variable->name, $scope);
    }

    private function assignedFromDecode(string $name, Node\FunctionLike $scope): bool
    {
        foreach ($this->nodeFinder->findInstanceOf((array) $scope->getStmts(), Expr\Assign::class) as $assign) {
            if ($assign->var instanceof Expr\Variable
                && $assign->var->name === $name
                && $this->callsFunction($assign->expr, self::DECODE)
            ) {
                return true;
            }
        }

        return false;
    }

    private function handedToEncode(string $name, Node\FunctionLike $scope): bool
    {
        foreach ($this->nodeFinder->findInstanceOf((array) $scope->getStmts(), Expr\FuncCall::class) as $call) {
            if (! $this->isNamed($call, self::ENCODE)) {
                continue;
            }

            foreach ($call->args as $arg) {
                if ($arg instanceof Node\Arg && $arg->value instanceof Expr\Variable && $arg->value->name === $name) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Whether the expression contains a call to the named function anywhere within it.
     */
    private function callsFunction(Node $expr, string $function): bool
    {
        foreach ($this->nodeFinder->findInstanceOf([$expr], Expr\FuncCall::class) as $call) {
            if ($this->isNamed($call, $function)) {
                return true;
            }
        }

        return false;
    }

    private function isNamed(Expr\FuncCall $call, string $function): bool
    {
        return $call->name instanceof Node\Name && strtolower($call->name->toString()) === $function;
    }
}
