<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Support\EdgeMap;

/**
 * What the program REACHES — every class and global function each unit ultimately talks to — at both
 * granularities a rule might ask about ({@see classes}, {@see scopes}). Two places doing the SAME JOB in
 * different words assemble the same resources, so a mechanism is known by what it reaches rather than by
 * the names its author chose. First-party classes are the pipes and can be followed; a vendor class or a
 * global function is a terminal — the alphabet a mechanism is written in.
 */
final class ResourceReach
{
    use MemoisedPerCodebase;

    /**
     * How far a first-party reference chain is followed for the CLASS population. At class granularity
     * this collapses fast — left to run, every class reaches every other through some container, and the
     * sets stop distinguishing anything — so direct reach is the default and this is the knob.
     */
    private const int DEPTH = 0;

    /**
     * How many classes may reach a first-party class before it counts as a HUB and reach stops being
     * followed THROUGH it. A hub is nobody's mechanism.
     */
    private const int HUB = 25;

    /**
     * What marks a global function apart from a class of the same spelling in the one resource alphabet.
     */
    private const string FUNCTION = 'fn:';

    /**
     * What marks a flag constant apart in the one resource alphabet.
     */
    private const string CONSTANT = 'const:';

    /**
     * @param  array<string, mixed>  $firstParty  the FQCNs this scan declares
     */
    private function __construct(
        private readonly Codebase $codebase,
        private readonly ResourcePopulation $classes,
        private readonly ResourcePopulation $scopes,
        private readonly array $firstParty,
    ) {}

    /**
     * The codebase this reach was read from — so a rule holding a reach can put a graph question to the
     * same program without threading it separately.
     */
    public function codebase(): Codebase
    {
        return $this->codebase;
    }

    protected static function build(Codebase $codebase): static
    {
        $byClass = [];
        $byScope = [];

        // The dependency EDGES in one selector — an import, a type, a `new`, a static call, a catch.
        foreach ($codebase->whereClassReference()->get() as $reference) {
            EdgeMap::link($byClass, $reference->enclosingClassName(), $reference->referencedClassName());

            // A method's own signature is NOT work it does. Counted, it hands every method four free
            // resources, and any two taking the same domain objects read as one mechanism on that alone.
            if ($reference->isSignatureType()) {
                continue;
            }

            EdgeMap::link($byScope, $reference->scope(), $reference->referencedClassName());
        }

        // A global function is a resource too, and often the most telling: `rename` and `getmypid` name
        // a mechanism far more sharply than any class the author wrapped them in.
        foreach ($codebase->whereFunction()->get() as $call) {
            EdgeMap::link($byClass, $call->enclosingClassName(), self::FUNCTION . $call->callName());
            EdgeMap::link($byScope, $call->scope(), self::FUNCTION . $call->callName());
        }

        // A flag is part of what a call DOES: the same `file_put_contents` appends or replaces by it.
        foreach ($codebase->whereConstant()->get() as $constant) {
            $name = $constant->constantName();

            if ($name === null) {
                continue;
            }

            EdgeMap::link($byClass, $constant->enclosingClassName(), self::CONSTANT . $name);
            EdgeMap::link($byScope, $constant->scope(), self::CONSTANT . $name);

        }

        $firstParty = $codebase->declarations();
        $closed = self::closed($byClass, $firstParty, self::holders($byClass));

        return new self(
            $codebase,
            new ResourcePopulation($closed, self::holders($closed)),
            new ResourcePopulation($byScope, self::holders($byScope)),
            $firstParty,
        );
    }

    /**
     * Reach counted over every CLASS — the granularity for a rule about whole collaborators.
     */
    public function classes(): ResourcePopulation
    {
        return $this->classes;
    }

    /**
     * Reach counted over every SCOPE — `Class::method`, and a function or file body — the granularity for
     * a rule about two code PATHS rather than two collaborators.
     */
    public function scopes(): ResourcePopulation
    {
        return $this->scopes;
    }

    /**
     * Is $resource something other than a VERB — a named type, or a flag constant? Only a verb is a step
     * a path takes. A shared type is the SUBJECT two units work on, and a flag is evidence they differ
     * (one appends where the other replaces), never evidence that one forgot something.
     */
    public function isType(string $resource): bool
    {
        return ! str_starts_with($resource, self::FUNCTION);
    }

    /**
     * Is $resource a TERMINAL — a global function or a class from outside this scan? Terminals are what a
     * mechanism is ultimately written in; a first-party class is a collaborator.
     */
    public function isTerminal(string $resource): bool
    {
        return ! isset($this->firstParty[$resource]);
    }

    /**
     * Close $direct over itself: fold a non-hub first-party target's resources into whatever reaches it,
     * to a fixed point or {@see DEPTH} rounds. Each round reads the PREVIOUS state, so a round is one step.
     *
     * @param  array<string, array<string, true>>  $direct
     * @param  array<string, mixed>  $firstParty
     * @param  array<string, int>  $reached  how many units reach each resource DIRECTLY, which is what
     *                                       marks a hub before any folding has blurred it
     * @return array<string, array<string, true>>
     */
    private static function closed(array $direct, array $firstParty, array $reached): array
    {
        $reach = $direct;

        for ($step = 0; $step < self::DEPTH; $step++) {
            $previous = $reach;
            $grew = false;

            foreach ($previous as $from => $resources) {
                foreach (array_keys($resources) as $resource) {
                    if (! isset($firstParty[$resource], $previous[$resource])) {
                        continue;
                    }

                    if (($reached[$resource] ?? 0) > self::HUB) {
                        continue; // a hub is reached THROUGH by everyone; its reach is nobody's mechanism
                    }

                    foreach (array_keys($previous[$resource]) as $reachedResource) {
                        if ($reachedResource === $from || isset($reach[$from][$reachedResource])) {
                            continue;
                        }

                        $reach[$from][$reachedResource] = true;
                        $grew = true;
                    }
                }
            }

            if (! $grew) {
                break;
            }
        }

        return $reach;
    }

    /**
     * @param  array<string, array<string, true>>  $reach
     * @return array<string, int>
     */
    private static function holders(array $reach): array
    {
        $holders = [];

        foreach ($reach as $resources) {
            foreach (array_keys($resources) as $resource) {
                $holders[$resource] = ($holders[$resource] ?? 0) + 1;
            }
        }

        return $holders;
    }
}
