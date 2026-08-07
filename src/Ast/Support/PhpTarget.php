<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

use JesseGall\CodeCommandments\Support\Path;
use JesseGall\CodeCommandments\Ast\Codebase;

/**
 * The MINIMUM PHP version the judged project commits to, read from its composer.json `require.php`.
 * A rule whose fix needs a language feature must not fire on a project that cannot use it yet.
 */
final class PhpTarget
{
    use MemoisedPerCodebase;

    /**
     * `clone($obj, [...])` — clone-with, the language feature that replaces a hand-rolled wither.
     */
    private const string CLONE_WITH = '8.5';

    private function __construct(private readonly ?string $minimum) {}

    /**
     * Can the project use clone-with? Unknown constraint (no composer.json in scope, or a constraint
     * we can't read) answers FALSE — a rule that would demand an unavailable feature stays quiet.
     */
    public function supportsCloneWith(): bool
    {
        return $this->minimum !== null && version_compare($this->minimum, self::CLONE_WITH, '>=');
    }

    protected static function build(Codebase $codebase): static
    {
        foreach ($codebase->files() as $file) {
            $manifest = self::manifestFor($file->path);

            if ($manifest !== null) {
                return new self(self::minimumOf($manifest));
            }
        }

        return new self(null);
    }

    /**
     * The nearest ancestor composer.json's decoded contents, or null.
     */
    private static function manifestFor(string $path): ?array
    {
        foreach (Path::selfAndAncestors(dirname($path)) as $dir) {
            if (! is_file($dir . '/composer.json')) {
                continue;
            }

            $decoded = json_decode((string) file_get_contents($dir . '/composer.json'), true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    /**
     * The lowest version a `require.php` constraint admits — `^8.5`, `>=8.4`, `~8.5.0` and `8.5.*`
     * all yield their leading `major.minor`. Null when there is no constraint to read.
     */
    private static function minimumOf(array $manifest): ?string
    {
        $constraint = $manifest['require']['php'] ?? null;

        if (! is_string($constraint)) {
            return null;
        }

        // A composite constraint (`>=8.2 <9.0`, `8.3 || 8.4`) is bounded by its lowest clause.
        $clauses = explode(' ', str_replace(['||', ',', '|'], ' ', $constraint));
        $lowest = null;

        foreach ($clauses as $clause) {
            $version = ltrim(trim($clause), '^~>=< ');
            $parts = explode('.', $version);

            if (! is_numeric($parts[0] ?? '')) {
                continue;
            }

            $normalised = $parts[0] . '.' . (is_numeric($parts[1] ?? '') ? $parts[1] : '0');
            $lowest = $lowest === null || version_compare($normalised, $lowest, '<') ? $normalised : $lowest;
        }

        return $lowest;
    }
}
