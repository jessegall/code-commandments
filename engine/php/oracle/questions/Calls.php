<?php

declare(strict_types=1);

namespace CodeCommandments\Oracle;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\ParsedFile;
use JesseGall\CodeCommandments\Ast\Support\Callee;
use JesseGall\CodeCommandments\Ast\Support\ChainResolver;
use JesseGall\CodeCommandments\Ast\Support\ReceiverResolver;
use JesseGall\CodeCommandments\Ast\Support\TypeResolver;
use JesseGall\CodeCommandments\Ast\TypeName;
use PhpParser\Node;

/**
 * What each call reaches: its receiver's type as ReceiverResolver reads it, and the declaration Callee finds for it.
 * And each expression inside a method as ChainResolver reads it, given the method's parameters' written types.
 */
final class Calls implements Question
{
    public function name(): string
    {
        return 'calls';
    }

    public function answers(Codebase $codebase, ParsedFile $file): iterable
    {
        $resolver = TypeResolver::forCodebase($codebase);
        $chains = ChainResolver::forCodebase($codebase);
        foreach (nodes($file->ast) as $node) {
            if ($node instanceof Node\Expr\New_ || $node instanceof Node\Expr\StaticCall || $node instanceof Node\Expr\MethodCall
                || $node instanceof Node\Expr\NullsafeMethodCall || $node instanceof Node\Expr\FuncCall) {
                $call = $codebase->wrap($node, $file);
                $callee = Callee::of($call, $resolver);
                yield [$node, 'call', [
                    'receiver' => ReceiverResolver::typeOf($call),
                    'callee' => $callee === null ? null : [$callee->class, $callee->method],
                    'name' => Callee::nameOf($call),
                ]];
            }
            if ($node instanceof Node\Expr && ($method = $this->method($node)) !== null) {
                yield [$node, 'chain', $chains->resolve($node, $this->paramTypes($method))];
            }
        }
    }

    private function method(Node $node): ?Node\Stmt\ClassMethod
    {
        foreach (AstNode::ancestorsOf($node) as $ancestor) {
            if ($ancestor instanceof Node\Stmt\ClassMethod) {
                return $ancestor;
            }
        }

        return null;
    }

    /** The parameter types FeatureEnvy hands the chain: each parameter's one written name. */
    private function paramTypes(Node\Stmt\ClassMethod $method): array
    {
        $types = [];
        foreach ($method->params as $param) {
            $type = TypeName::simpleName($param->type);
            if ($type !== null && AstNode::variableNameOf($param->var) !== null) {
                $types[$param->var->name] = $type;
            }
        }

        return $types;
    }
}
