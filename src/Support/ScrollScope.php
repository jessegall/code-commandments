<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

/**
 * THE single authority for "does this file belong to a scroll?" — its configured
 * `path`, `extensions`, and `exclude` (configured + always-on default excludes via
 * {@see PathExcludeMatcher}).
 *
 * Every git-derived candidate set (judge --git / --staged, the pilgrimage's
 * branch/staged scope) passes through here, so an out-of-path or EXCLUDED file can
 * never be judged or resolved no matter how it was discovered. The directory-scan
 * path ({@see \JesseGall\CodeCommandments\Scanners\GenericFileScanner}) already
 * applies the same excludes at walk time; this resolver brings the git-derived
 * paths under the identical rule, so the walk, the push gate, and absolve/report
 * always judge the SAME file set.
 *
 * The only intentional bypass is the explicit `--path` escape hatch (judgePath).
 */
final class ScrollScope
{
    /**
     * @param  string  $scrollPath  resolved ABSOLUTE scroll root, or '' for no path restriction
     * @param  list<string>  $extensions
     * @param  list<string>  $exclude
     */
    private function __construct(
        private readonly string $scrollPath,
        private readonly array $extensions,
        private readonly array $exclude,
    ) {}

    /**
     * Build from a scroll config array. A relative `path` is resolved against
     * $basePath (falling back to a cwd-relative resolve), matching how the
     * scanners interpret it.
     *
     * @param  array<string, mixed>  $scrollConfig
     */
    public static function fromConfig(string $basePath, array $scrollConfig): self
    {
        $path = (string) ($scrollConfig['path'] ?? $basePath);

        // A relative `path` is resolved against $basePath FIRST (not the cwd) — the
        // scroll is defined relative to the project root, and trusting cwd would let
        // a same-named directory elsewhere (e.g. the running tool's own `src/`)
        // hijack the scope.
        if (! str_starts_with($path, '/')) {
            $path = rtrim($basePath, '/') . '/' . $path;
        }

        $resolved = realpath($path);

        /** @var list<string> $extensions */
        $extensions = is_array($scrollConfig['extensions'] ?? null) ? array_values($scrollConfig['extensions']) : [];
        /** @var list<string> $exclude */
        $exclude = is_array($scrollConfig['exclude'] ?? null) ? array_values($scrollConfig['exclude']) : [];

        return new self($resolved === false ? '' : $resolved, $extensions, $exclude);
    }

    /**
     * Whether $file belongs to this scroll: a real file under the scroll path,
     * with a configured extension, and NOT excluded (configured + default excludes).
     */
    public function includes(string $file): bool
    {
        $real = realpath($file);

        if ($real === false || ! is_file($real)) {
            return false;
        }

        if ($this->scrollPath !== '' && ! str_starts_with($real, $this->scrollPath)) {
            return false;
        }

        if (PathExcludeMatcher::shouldExclude($real, $this->exclude)) {
            return false;
        }

        $extension = pathinfo($real, PATHINFO_EXTENSION);

        if ($this->extensions !== [] && ! in_array($extension, $this->extensions, true)) {
            return false;
        }

        return true;
    }

    /** The inverse of {@see self::includes()} — true when the file is out of scope / excluded. */
    public function excludes(string $file): bool
    {
        return ! $this->includes($file);
    }

    /**
     * Keep only the files that belong to this scroll.
     *
     * @param  iterable<string>  $files
     * @return list<string>
     */
    public function filter(iterable $files): array
    {
        $kept = [];

        foreach ($files as $file) {
            if ($this->includes($file)) {
                $kept[] = $file;
            }
        }

        return $kept;
    }
}
