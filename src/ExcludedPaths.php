<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

/**
 * The ONE home of "is this file under an excluded path" — a project's {@see Config::exclude} list
 * resolved against its root, asked both while WALKING the tree (so an excluded subtree is never
 * read) and while reporting. A plain entry matches on a boundary, so `src/Foo` never swallows
 * `src/FooBar`; an entry containing a star is a glob, which is what a monorepo wants — one line for
 * every sub-project's build output, a double star to reach any depth. Matching covers everything
 * beneath. Empty by default.
 */
final class ExcludedPaths
{
    /**
     * Bare, this covers nothing — the form a scan that was told nothing can default to.
     *
     * @param  list<string>  $prefixes  absolute path prefixes (no trailing slash)
     * @param  list<string>  $patterns  globs, relative to the root
     */
    public function __construct(
        private readonly string $root = '',
        private readonly array $prefixes = [],
        private readonly array $patterns = [],
    ) {}

    public static function none(): self
    {
        return new self;
    }

    /**
     * Resolve a project's relative exclude() list against $root. A plain path is canonicalised to
     * its realpath when it exists on disk (so the same file compares equal however it was reached),
     * else kept as the plain concatenation (an excluded path that isn't present yet simply never
     * matches). A glob is kept as written and matched per-path.
     *
     * @param  list<string>  $relative
     */
    public static function under(string $root, array $relative): self
    {
        $root = rtrim(realpath($root) ?: $root, '/');
        $prefixes = [];
        $patterns = [];

        foreach ($relative as $path) {
            $path = trim($path, '/');

            if ($path === '') {
                continue;
            }

            if (str_contains($path, '*')) {
                $patterns[] = $path;

                continue;
            }

            $absolute = $root . '/' . $path;
            $prefixes[] = realpath($absolute) ?: $absolute;
        }

        return new self($root, array_values($prefixes), array_values($patterns));
    }

    public function isEmpty(): bool
    {
        return $this->prefixes === [] && $this->patterns === [];
    }

    /**
     * Is $path an excluded path, or nested beneath one?
     */
    public function covers(string $path): bool
    {
        if ($this->isEmpty()) {
            return false;
        }

        $path = realpath($path) ?: rtrim($path, '/');

        foreach ($this->prefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        return $this->matchedByGlob($path);
    }

    /**
     * Does a glob cover $path — either matching it, or matching a directory it sits under? The
     * ancestors are walked, so a glob naming a folder excludes its whole contents the way a plain
     * entry does.
     */
    private function matchedByGlob(string $path): bool
    {
        if ($this->patterns === [] || ! str_starts_with($path, $this->root . '/')) {
            return false;
        }

        $candidate = substr($path, strlen($this->root) + 1);

        while ($candidate !== '' && $candidate !== '.' && $candidate !== '/') {
            foreach ($this->patterns as $pattern) {
                // `**` is the any-depth form, so `*` is allowed to cross a separator for it;
                // otherwise a `*` stays within one segment, which is what a reader expects.
                $flags = str_contains($pattern, '**') ? 0 : FNM_PATHNAME;

                if (fnmatch($pattern, $candidate, $flags)) {
                    return true;
                }
            }

            $parent = dirname($candidate);

            if ($parent === $candidate) {
                break;
            }

            $candidate = $parent;
        }

        return false;
    }
}
