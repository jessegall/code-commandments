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
     * Every registered package's exemptions, aggregated: the shipped roster plus any the consumer's
     * config named. Built ONCE and handed to the scan that will consult it ({@see Codebase}), so no
     * caller has to push a package list into a static before the first detector runs — which made
     * the order those two things happened in load-bearing.
     *
     * @param  class-string<Package>  ...$consumers
     */
    public static function forPackages(string ...$consumers): self
    {
        $registry = new self();

        foreach ([...Catalog::all(), ...array_map(static fn (string $class): Package => new $class, $consumers)] as $package) {
            $package->register($registry);
        }

        return $registry;
    }

    /**
     * Is ($class, $method) exempt under $tag across every registered package? The one call a
     * detector makes — `Exemptions::has(Boundary::class, $codebase, $class)` (or `has('boundary', …)`).
     *
     * @param  class-string|string  $tag
     */
    public function has(string $tag, Codebase $codebase, ?string $class, ?string $method = null): bool
    {
        $clause = $this->clauses[Exemption::resolve($tag)] ?? null;

        return $clause !== null && $clause->matches($codebase, $class, $method);
    }

    /**
     * Is a reference sitting inside $attribute exempt under $tag — `#[ObservedBy(...)]` naming the
     * other end of a binding the framework mandates? The attribute twin of {@see has}: a reference
     * made BY a declaration's attribute is not the declaring code's own choice (#450).
     *
     * @param  class-string|string  $tag
     */
    public function hasAttribute(string $tag, Codebase $codebase, ?string $attribute): bool
    {
        $clause = $this->clauses[Exemption::resolve($tag)] ?? null;

        return $clause !== null && $clause->matchesAttribute($codebase, $attribute);
    }
}
