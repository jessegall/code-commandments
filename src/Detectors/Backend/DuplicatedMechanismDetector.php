<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Ast\Support\ReachedUnit;
use JesseGall\CodeCommandments\Ast\Support\ResourceReach;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Codebase as BaseCodebase;
use JesseGall\CodeCommandments\Detectors\RecurrenceDetector;
use JesseGall\CodeCommandments\Located;
use JesseGall\CodeCommandments\Sins\Backend\DuplicatedMechanism;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Unpublished;

/**
 * Flags classes in separate namespaces that do the same JOB in different words — one mechanism assembled
 * twice. A mechanism is read as the rare VERBS a class reaches ({@see ResourceReach}): what it does to the
 * world, never what its author called it, so two spellings of one decision still meet. Where a
 * {@see RecurringPattern} buckets shapes that are IDENTICAL, this grows groups that are merely SIMILAR.
 */
final class DuplicatedMechanismDetector implements Detector, RecurrenceDetector, Unpublished
{
    /**
     * How big a class must be to be doing something worth naming. A mechanism is a CLUSTER; a small class
     * that happens to reach two odd things is a coincidence.
     */
    private const int MIN_LINES = 25;

    /**
     * What share of the classes may reach a verb before it stops telling us anything. Past this it is
     * the program's background — `array_map`, `sprintf`, `is_string`, which every program repeats and
     * which say nothing about what a class DOES. A share, not a count, so a fixture and a monorepo
     * answer alike.
     */
    private const float MAX_SHARE = 0.01;

    /**
     * How many verbs two classes must share before they are doing one job rather than brushing past each
     * other. `rename` alone is a coincidence; `rename` + `getmypid` + `var_export` + `glob` is a decision.
     */
    private const int MIN_SHARED = 4;

    /**
     * How many of the shared resources must be VERBS — global functions. A shared vendor TYPE is
     * vocabulary, not a mechanism: two classes both naming `PhpParser\Node\Expr` work on the same data,
     * which says what they are about, never that they do the same thing to it.
     */
    private const int MIN_VERBS = 2;

    /**
     * How much the shared verbs must TELL us before they are a mechanism. Counting them cannot decide it:
     * four verbs every file-touching class reaches say only that all of them touch files, while `rename`
     * and `getmypid` together all but name the thing. Rarity is what separates the two.
     */
    private const float MIN_WEIGHT = 30.0;

    /**
     * @var array<string, string>  location => the cluster it was placed in, kept from the last run so a
     *                             finding can name its group
     */
    private array $clusters = [];

    public function sin(): Sin
    {
        return new DuplicatedMechanism();
    }

    public function groupKey(Located $finding, BaseCodebase $codebase): ?string
    {
        return $this->clusters[$finding->location()] ?? null;
    }

    public function find(Codebase $codebase): array
    {
        $this->clusters = [];

        $units = $this->units($codebase);

        $findings = [];

        foreach ($this->clustered($units, $codebase) as $cluster) {
            $shared = $this->sharedBy($cluster, $units);

            foreach ($cluster as $location) {
                $this->clusters[$location] = implode('|', $shared);
                $findings[] = $units[$location]->match;
            }
        }

        return $findings;
    }

    /**
     * Every class big enough to hold a mechanism, with the rare verbs it reaches — the sets the whole
     * verdict is drawn from, gathered once.
     *
     * @return array<string, ReachedUnit>
     */
    private function units(Codebase $codebase): array
    {
        $reach = ResourceReach::forCodebase($codebase);

        $units = [];

        $declarations = $codebase->whereClass()
            ->where(static fn (AstNode $node): bool => $node->lineCount() >= self::MIN_LINES)
            ->get();

        foreach ($declarations as $declaration) {
            $rare = $reach->classes()->rareOf($declaration->declaredClassName(), self::MAX_SHARE);
            $verbs = array_values(array_filter($rare, static fn (string $r): bool => $reach->isTerminal($r)));

            if (count($verbs) >= self::MIN_SHARED) {
                $units[$declaration->location()] = new ReachedUnit($declaration, $verbs);
            }
        }

        return $units;
    }

    /**
     * Every pair of units doing one job, strongest overlap first, so the clearest mechanism seeds its
     * group before a weaker one can claim a member. Paired through the verbs themselves, so two unrelated
     * units are never compared.
     *
     * @param  array<string, ReachedUnit>  $units
     * @return list<array{string, string}>
     */
    private function pairs(array $units, Codebase $codebase): array
    {
        $holders = [];

        foreach ($units as $location => $unit) {
            foreach ($unit->resources as $verb) {
                $holders[$verb][] = $location;
            }
        }

        $overlap = [];

        foreach ($holders as $locations) {
            foreach ($locations as $index => $one) {
                foreach (array_slice($locations, $index + 1) as $other) {
                    $pair = $one < $other ? "{$one}\0{$other}" : "{$other}\0{$one}";
                    $overlap[$pair] = ($overlap[$pair] ?? 0) + 1;
                }
            }
        }

        $overlap = array_filter($overlap, static fn (int $count): bool => $count >= self::MIN_SHARED);
        arsort($overlap);

        $pairs = [];

        foreach (array_keys($overlap) as $pair) {
            [$one, $other] = explode("\0", (string) $pair);

            if ($this->arePeers($units[$one]->match, $units[$other]->match, $codebase)) {
                $pairs[] = [$one, $other];
            }
        }

        return $pairs;
    }

    /**
     * Are these two INDEPENDENT implementations, rather than one module's parts or one class and the
     * collaborator it hands the job to?
     */
    private function arePeers(NodeMatch $one, NodeMatch $other, Codebase $codebase): bool
    {
        if ($one->namespaceName() === $other->namespaceName()) {
            return false;
        }

        $reach = ResourceReach::forCodebase($codebase);

        return ! isset($reach->classes()->of($one->declaredClassName())[(string) $other->declaredClassName()])
            && ! isset($reach->classes()->of($other->declaredClassName())[(string) $one->declaredClassName()]);
    }

    /**
     * The pairs grown into groups — a mechanism written in four places is ONE finding about four classes.
     * A member joins only while the verbs the WHOLE group shares stay a mechanism, because similarity does
     * not chain: were a group merely linked pair to pair, its ends would have nothing in common and the
     * thing it was supposed to name would be empty.
     *
     * @param  array<string, ReachedUnit>  $units
     * @return list<list<string>>
     */
    private function clustered(array $units, Codebase $codebase): array
    {
        $taken = [];
        $clusters = [];

        foreach ($this->pairs($units, $codebase) as [$one, $other]) {
            if (isset($taken[$one]) || isset($taken[$other])) {
                continue;
            }

            $shared = $units[$one]->sharedWith($units[$other]);

            if (! $this->isMechanism($shared, $codebase)) {
                continue;
            }

            $cluster = [$one, $other];
            $taken[$one] = $taken[$other] = true;

            foreach ($units as $location => $unit) {
                if (isset($taken[$location])) {
                    continue;
                }

                $joined = array_values(array_intersect($shared, $unit->resources));

                if (! $this->isMechanism($joined, $codebase)) {
                    continue;
                }

                if (! $this->joins($cluster, $location, $units, $codebase)) {
                    continue;
                }

                $cluster[] = $location;
                $shared = $joined;
                $taken[$location] = true;
            }

            $clusters[] = $cluster;
        }

        return $clusters;
    }

    /**
     * Is $location a peer of EVERY member already in $cluster? A group is a set of mutual twins, so one
     * that merely resembles a single member does not belong in it.
     *
     * @param  list<string>  $cluster
     * @param  array<string, ReachedUnit>  $units
     */
    private function joins(array $cluster, string $location, array $units, Codebase $codebase): bool
    {
        foreach ($cluster as $member) {
            if (! $this->arePeers($units[$member]->match, $units[$location]->match, $codebase)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Is what these units share a MECHANISM — enough resources, enough of them verbs rather than the
     * vocabulary of a shared subject, and rare enough between them to name one thing?
     *
     * @param  array<int, string>  $shared
     */
    private function isMechanism(array $shared, Codebase $codebase): bool
    {
        $reach = ResourceReach::forCodebase($codebase);

        if (count($shared) < self::MIN_SHARED) {
            return false;
        }

        $verbs = array_filter($shared, static fn (string $r): bool => ! $reach->isType($r));

        if (count($verbs) < self::MIN_VERBS) {
            return false;
        }

        // Weighed over the VERBS alone: a shared type corroborates a mechanism but never carries one, and
        // counting types lets a pile of shared vocabulary reach the bar on its own.
        return array_sum(array_map(static fn (string $r): float => $reach->classes()->weightOf($r), $verbs)) >= self::MIN_WEIGHT;
    }

    /**
     * The verbs every member of $cluster reaches — the mechanism itself, as the finding should name it.
     *
     * @param  list<string>  $cluster
     * @param  array<string, ReachedUnit>  $units
     * @return list<string>
     */
    private function sharedBy(array $cluster, array $units): array
    {
        $shared = null;

        foreach ($cluster as $location) {
            $verbs = $units[$location]->resources;
            $shared = $shared === null ? $verbs : array_intersect($shared, $verbs);
        }

        return array_values((array) $shared);
    }
}
