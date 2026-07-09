<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

/**
 * The set of paths a run excludes — a project's {@see Config::exclude} list resolved to absolute
 * prefixes against the project root. The ONE home of "is this file under an excluded path": a file
 * is covered when it IS an excluded path or nests beneath one (a boundary match, so `src/Foo` never
 * swallows `src/FooBar`). Empty by default (covers nothing).
 */
final class ExcludedPaths
{
    /**
     * @param  list<string>  $prefixes  absolute path prefixes (no trailing slash)
     */
    private function __construct(private readonly array $prefixes) {}

    public static function none(): self
    {
        return new self([]);
    }

    /**
     * Resolve a project's relative exclude() list to absolute prefixes under $root. A path is
     * canonicalised to its realpath when it exists on disk (so the same file compares equal however
     * it was reached), else kept as the plain concatenation (an excluded path that isn't present yet
     * simply never matches).
     *
     * @param  list<string>  $relative
     */
    public static function under(string $root, array $relative): self
    {
        $root = rtrim($root, '/');
        $prefixes = [];

        foreach ($relative as $path) {
            $path = trim($path, '/');

            if ($path === '') {
                continue;
            }

            $absolute = $root . '/' . $path;
            $prefixes[] = realpath($absolute) ?: $absolute;
        }

        return new self(array_values($prefixes));
    }

    public function isEmpty(): bool
    {
        return $this->prefixes === [];
    }

    /**
     * Is $path an excluded path, or nested beneath one?
     */
    public function covers(string $path): bool
    {
        if ($this->prefixes === []) {
            return false;
        }

        $path = realpath($path) ?: rtrim($path, '/');

        foreach ($this->prefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }
}
