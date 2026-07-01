<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Packages;

use JesseGall\CodeCommandments\Ast\Codebase;

/**
 * The open, tag-keyed exemption registry — how a package tells a detector "leave this alone",
 * without either side importing the other. A package builds exemptions in {@see Package::register}
 * (`$ex->exempt(Tag::class)->on(...)`), keyed by a tag class-string BOTH sides agree on; a detector
 * asks {@see has} under its tag. The built-in tags live in {@see Tags}; a custom detector declares
 * its own {@see Exemption} subclass as a tag ({@see Exemption::resolve} enforces that — never a
 * random class) and any package (yours or a third party's) can register against it.
 *
 * This is the twin of {@see Catalog} for cross-detector policy: one mechanism, extensible by
 * anyone, no fixed list of exemption kinds.
 */
final class Exemptions
{
    /** @var array<class-string, Clause> tag => its clause */
    private array $clauses = [];

    /** The aggregated registry (every package's registrations), built once. */
    private static ?self $registry = null;

    /** @var list<class-string<Package>> Consumer packages registered via config, beyond the shipped roster. */
    private static array $extra = [];

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
