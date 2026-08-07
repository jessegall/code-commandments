<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Support\ComposerManifest;
use JesseGall\PhpTypes\Option;

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

    /**
     * @param  Option<string>  $minimum
     */
    private function __construct(private readonly Option $minimum) {}

    /**
     * Can the project use clone-with? Unknown constraint (no composer.json in scope, or a constraint
     * we can't read) answers FALSE — a rule that would demand an unavailable feature stays quiet.
     */
    public function supportsCloneWith(): bool
    {
        return $this->minimum->isSomeAnd(static fn (string $version): bool => version_compare($version, self::CLONE_WITH, '>='));
    }

    protected static function build(Codebase $codebase): static
    {
        foreach ($codebase->files() as $file) {
            foreach (ComposerManifest::nearest($file->path) as $manifest) {
                return new self($manifest->phpConstraint()->andThen(self::lowestOf(...)));
            }
        }

        return new self(Option::none());
    }

    /**
     * The lowest version a `require.php` constraint admits — `^8.5`, `>=8.4`, `~8.5.0` and `8.5.*`
     * all yield their leading `major.minor`. None when no clause reads as a version.
     *
     * @return Option<string>
     */
    private static function lowestOf(string $constraint): Option
    {
        // A composite constraint (`>=8.2 <9.0`, `8.3 || 8.4`) is bounded by its lowest clause.
        $clauses = explode(' ', str_replace(['||', ',', '|'], ' ', $constraint));
        $lowest = null;

        foreach ($clauses as $clause) {
            $version = ltrim(trim($clause), '^~>=< ');
            $parts = explode('.', $version);

            // `explode` always yields a first part, so the major is simply there; only the MINOR
            // can be missing (`^8`), and that is the one absence worth stating.
            if (! is_numeric($parts[0])) {
                continue;
            }

            $minor = count($parts) > 1 && is_numeric($parts[1]) ? $parts[1] : '0';
            $normalised = $parts[0] . '.' . $minor;
            $lowest = $lowest === null || version_compare($normalised, $lowest, '<') ? $normalised : $lowest;
        }

        return Option::fromNullable($lowest);
    }
}
