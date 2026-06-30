<?php

namespace Shop\Routing;

use JesseGall\CodeCommandments\Sins\Backend\ScratchStateRestore;

use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Pushes a path prefix onto `$this`, builds the nested routes, then restores the
 * prefix in `finally`. The prefix is per-call context — the try/finally exists
 * only because the field can't say "this is mine for this call".
 *
 * @return list<string>
 */
final class PrefixStack
{
    private string $prefix = '';

    /**
     * @param  list<string>  $routes
     * @return list<string>
     */
    #[Sinful(ScratchStateRestore::class)]
    public function nest(string $segment, array $routes): array
    {
        $parent = $this->prefix;
        $this->prefix = ltrim($parent . '/' . $segment, '/');

        try {
            return array_map(fn (string $route): string => $this->prefix . '#' . $route, $routes);
        } finally {
            $this->prefix = $parent;
        }
    }

    /**
     * @param  list<string>  $routes
     * @return list<string>
     */
    #[Righteous(ScratchStateRestore::class)]
    public function nestUnder(string $prefix, string $segment, array $routes): array
    {
        $nested = ltrim($prefix . '/' . $segment, '/');

        return array_map(fn (string $route): string => $nested . '#' . $route, $routes);
    }
}
