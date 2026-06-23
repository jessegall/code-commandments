<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Resolvers\Ast;

use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * Recognise a "keyed read" — a string-keyed fetch off an untyped bag, like
 * `$request->get('id')`, `$request->input('id')`, or `$request['id']` — and pull
 * its parts into a {@see KeyedRead}.
 */
final class KeyedReadResolver
{
    /** The default untyped bag getters a keyed read uses. */
    public const DEFAULT_GETTERS = ['get', 'input', 'query'];

    /**
     * The {@see KeyedRead} $node represents (a string-keyed getter call or array
     * access), or null when it is not one. Array access (`$x['k']`) reports the
     * getter as `get`.
     *
     * @param  list<string>  $getters  method names that count as untyped getters
     */
    public static function resolve(Expr $node, array $getters = self::DEFAULT_GETTERS): ?KeyedRead
    {
        if ($node instanceof Expr\MethodCall
            && $node->name instanceof Node\Identifier
            && in_array($node->name->toString(), $getters, true)
        ) {
            $key = $node->args[0] ?? null;

            if ($key instanceof Node\Arg && $key->value instanceof Node\Scalar\String_) {
                $default = isset($node->args[1]) && $node->args[1] instanceof Node\Arg ? $node->args[1]->value : null;

                return new KeyedRead($node->var, $key->value->value, $default, $node->name->toString());
            }
        }

        if ($node instanceof Expr\ArrayDimFetch
            && $node->dim instanceof Node\Scalar\String_
            && ($node->var instanceof Expr\Variable || $node->var instanceof Expr\PropertyFetch)
        ) {
            return new KeyedRead($node->var, $node->dim->value, null, 'get');
        }

        return null;
    }
}
