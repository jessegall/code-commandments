<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Concurrent;

use JesseGall\CodeCommandments\Ast\NodeMatch;

/**
 * The `jessegall/concurrent` package's knowledge, as a node: everything a detector needs to know
 * about the `Concurrent` shared-state proxy lives here, so a rule reads `$n->extendsConcurrent()`
 * and the package FQCN is stated once. Reached by type-hinting it in a `where` closure — the query
 * injects it ({@see \JesseGall\CodeCommandments\Ast\Query::where}).
 */
final class ConcurrentNode extends NodeMatch
{
    private const string CONCURRENT = 'JesseGall\\Concurrent\\Concurrent';

    /**
     * Is this class declaration a subclass of the package's `Concurrent` proxy — inheriting the
     * thread-safe wrapper instead of composing it? Resolved through the class graph, so a
     * transitive subclass counts and a codebase without the package never matches.
     */
    public function extendsConcurrent(): bool
    {
        return $this->codebase->extends($this->enclosingClassName(), self::CONCURRENT);
    }
}
