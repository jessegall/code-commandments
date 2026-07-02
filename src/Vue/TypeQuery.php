<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

use Closure;
use JesseGall\CodeCommandments\Query as BaseQuery;

/**
 * The fluent query over DECLARATION space — the same shared {@see BaseQuery}
 * (`where`/`reject`, the filter loop, decorator injection) the template {@see Query}
 * uses, only its candidates are {@see TypeDeclaration}s and its match a
 * {@see TypeDeclarationMatch}. A detector over types reads exactly like one over
 * elements; just the nodes differ.
 *
 * The selector sees the raw {@see TypeDeclaration}; `where`/`reject` see the match.
 */
final class TypeQuery extends BaseQuery
{
    /**
     * @param  Closure(): list<TypeDeclaration>  $declarations  the declarations this query draws from
     * @param  Closure(TypeDeclaration): bool  $selector
     */
    public function __construct(
        private readonly Closure $declarations,
        private readonly Closure $selector,
    ) {}

    /**
     * Keep declarations of at least $fields fields — the floor that separates a real
     * shape from a trivial one-or-two-field look-alike a name coincidence could match.
     */
    public function havingAtLeastFields(int $fields): self
    {
        return $this->filter(static fn (TypeDeclarationMatch $match): bool => $match->fieldCount() >= $fields);
    }

    protected function selected(): iterable
    {
        foreach (($this->declarations)() as $declaration) {
            if (($this->selector)($declaration)) {
                yield $declaration;
            }
        }
    }

    protected function wrap(mixed $candidate, ?string $as): object
    {
        $class = $as ?? TypeDeclarationMatch::class;

        return new $class($candidate);
    }

    protected function matchClass(): string
    {
        return TypeDeclarationMatch::class;
    }
}
