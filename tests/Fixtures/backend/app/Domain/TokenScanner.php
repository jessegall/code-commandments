<?php

namespace Shop\Domain;

use JesseGall\CodeCommandments\Sins\Backend\RepeatedTypeGuard;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;

/*
 * The same `$t instanceof Bracket && $t->pair instanceof Bracket` narrowing is copied into two methods —
 * a guard with no name. It should be a named predicate.
 */
final class TokenScanner
{
    #[Sinful(RepeatedTypeGuard::class)]
    public function opens($t): bool
    {
        return $t instanceof Bracket && $t->pair instanceof Bracket;
    }

    /**
     * @param  list<mixed>  $tokens
     */
    #[Sinful(RepeatedTypeGuard::class)]
    public function pairs(array $tokens): int
    {
        $count = 0;

        foreach ($tokens as $t) {
            $count += $t instanceof Bracket && $t->pair instanceof Bracket ? 1 : 0;
        }

        return $count;
    }

    /**
     * The narrowing promoted to a NAME: `isBalancedBrace()` holds `$t instanceof Brace &&
     * $t->close instanceof Brace` once, and every site asks the predicate instead of copying the chain.
     *
     * @param  list<mixed>  $tokens
     */
    #[Fixed(RepeatedTypeGuard::class)]
    public function balanced(array $tokens): int
    {
        return count(array_filter($tokens, $this->isBalancedBrace(...)));
    }

    private function isBalancedBrace($t): bool
    {
        return $t instanceof Brace && $t->close instanceof Brace;
    }
}
