<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Packages;

use JesseGall\CodeCommandments\Ast\Codebase;

/**
 * Tag-keyed exemption registry that decouples packages from detectors. Packages register
 * exemptions via tag class-string, detectors query under a tag—extensible, no fixed list.
 */
final class Exemptions
{
    /**
     * The aggregated registry (every package's registrations), built once.
     */
    private static ?self $registry = null;

    /**
     * @var list<class-string<Package>> Consumer packages registered via config, beyond the shipped roster.
     */
    private static array $extra = [];

    /**
     * @var array<class-string, Clause> tag => its clause
     */
    private array $clauses = [];

    /**
     * Open (or continue) the exemption clause for a tag, to add rules to it. The tag is a
     * class-string OR the {@see Exemption::slug} of a built-in — a package developer can write
     * `exempt('boundary')` instead of `exempt(Boundary::class)`.
     *
     * @param  class-string|string  $tag
     */
    public function exempt(string $tag): Clause
    {
        return $this->clauses[Exemption::resolve($tag)] ??= new Clause();
    }

    /**
     * Register the consumer's own {@see Package} classes (from `Config::package(...)`), beyond the
     * shipped roster — the CLI calls this once, before any detector runs, so their exemptions are
     * live for the scan. Rebuilds the aggregated registry on the next query.
     *
     * @param  class-string<Package>  ...$packages
     */
    public static function usePackages(string ...$packages): void
    {
        self::$extra = $packages;
        self::$registry = null;
    }

    /**
     * Is ($class, $method) exempt under $tag across every registered package? The one call a
     * detector makes — `Exemptions::has(Boundary::class, $codebase, $class)` (or `has('boundary', …)`).
     *
     * @param  class-string|string  $tag
     */
    public static function has(string $tag, Codebase $codebase, ?string $class, ?string $method = null): bool
    {
        $clause = self::registry()->clauses[Exemption::resolve($tag)] ?? null;

        return $clause !== null && $clause->matches($codebase, $class, $method);
    }

    /**
     * Is a reference sitting inside $attribute exempt under $tag — `#[ObservedBy(...)]` naming the
     * other end of a binding the framework mandates? The attribute twin of {@see has}: a reference
     * made BY a declaration's attribute is not the declaring code's own choice (#450).
     *
     * @param  class-string|string  $tag
     */
    public static function hasAttribute(string $tag, Codebase $codebase, ?string $attribute): bool
    {
        $clause = self::registry()->clauses[Exemption::resolve($tag)] ?? null;

        return $clause !== null && $clause->matchesAttribute($codebase, $attribute);
    }

    private static function registry(): self
    {
        if (self::$registry !== null) {
            return self::$registry;
        }

        $registry = new self();

        foreach ([...Catalog::all(), ...array_map(static fn (string $class): Package => new $class, self::$extra)] as $package) {
            $package->register($registry);
        }

        return self::$registry = $registry;
    }
}
