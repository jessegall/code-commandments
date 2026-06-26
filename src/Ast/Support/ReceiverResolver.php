<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

use JesseGall\CodeCommandments\Ast\AstNode;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\Property;

/**
 * Best-effort static type of a method call's receiver: `$this`, a typed
 * parameter, or a typed `$this->property`. Returns null when it can't be
 * resolved cheaply (so a detector stays conservative rather than guessing).
 */
final class ReceiverResolver
{
    public static function typeOf(AstNode $match): ?string
    {
        $node = $match->node;

        if (! ($node instanceof MethodCall || $node instanceof NullsafeMethodCall)) {
            return null;
        }

        $receiver = $node->var;

        if ($receiver instanceof Variable) {
            if ($receiver->name === 'this') {
                return $match->enclosingClassName();
            }

            return is_string($receiver->name) ? self::paramType($match, $receiver->name) : null;
        }

        if ($receiver instanceof PropertyFetch
            && $receiver->var instanceof Variable
            && $receiver->var->name === 'this'
            && $receiver->name instanceof Identifier) {
            return self::propertyType($match, $receiver->name->toString());
        }

        return null;
    }

    private static function paramType(AstNode $match, string $var): ?string
    {
        $function = $match->enclosingFunction();

        if ($function === null) {
            return null;
        }

        foreach ($function->getParams() as $param) {
            if ($param->var instanceof Variable && $param->var->name === $var) {
                return self::typeName($param->type);
            }
        }

        return null;
    }

    private static function propertyType(AstNode $match, string $name): ?string
    {
        $class = $match->enclosingClass();

        if ($class === null) {
            return null;
        }

        $constructor = $class->getMethod('__construct');

        if ($constructor !== null) {
            foreach ($constructor->getParams() as $param) {
                if ($param->flags !== 0 && $param->var instanceof Variable && $param->var->name === $name) {
                    return self::typeName($param->type);
                }
            }
        }

        foreach ($class->stmts as $stmt) {
            if (! $stmt instanceof Property) {
                continue;
            }

            foreach ($stmt->props as $property) {
                if ($property->name->toString() === $name) {
                    return self::typeName($stmt->type);
                }
            }
        }

        return null;
    }

    private static function typeName(?Node $type): ?string
    {
        if ($type instanceof NullableType) {
            return self::typeName($type->type);
        }

        if ($type instanceof Name) {
            return $type->toString();
        }

        return null;
    }
}
