<?php

namespace Shop\Domain;

use JesseGall\CodeCommandments\Sins\Backend\RepeatedGuard;
use JesseGall\CodeCommandments\Testing\Sinful;

/*
 * The same two-`array_key_exists` presence guard is copied into `get` and `has` — a compound condition with
 * no name. It wants a named predicate (`hasValueFor(...)`), not a copied `&&`.
 */
final class FrameLookup
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $frames = [];

    #[Sinful(RepeatedGuard::class)]
    public function get(string $node, string $port): mixed
    {
        if (array_key_exists($node, $this->frames) && array_key_exists($port, $this->frames[$node])) {
            return $this->frames[$node][$port];
        }

        return null;
    }

    #[Sinful(RepeatedGuard::class)]
    public function has(string $node, string $port): bool
    {
        return array_key_exists($node, $this->frames) && array_key_exists($port, $this->frames[$node]);
    }
}
