<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Resolvers\Ast;

use PhpParser\Node\Expr;

/**
 * A string-keyed fetch off an untyped bag — `$request->get('id', $default)`,
 * `$request->input('id')`, or `$request['id']` — decomposed into its parts.
 */
final readonly class KeyedRead
{
    public function __construct(
        /** The receiver the key is read from (e.g. `$request`). */
        public Expr $receiver,
        /** The string key being read. */
        public string $key,
        /** The default expression (the getter's 2nd arg), or null when none. */
        public ?Expr $default,
        /** The getter method name (`get`/`input`/`query`; `get` for array access). */
        public string $getter,
    ) {}
}
