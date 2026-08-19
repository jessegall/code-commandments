<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detector;
use JesseGall\CodeCommandments\WholeTree;

/**
 * The detectors that read BEYOND the file in hand — those asking {@see Codebase} a question no single
 * file answers, directly or through what they call. Read from the SOURCE, so a consumer's own rule in
 * `.commandments/custom/` is classified beside the shipped ones. Evidence, not proof: a question put
 * through a property (`$match->codebase->index()`) resolves to no receiver and is missed, so a
 * {@see WholeTree} marker counts too and anything unseen is taken to read the world.
 */
final class CrossFileSet
{
    /**
     * The {@see Codebase} surface whose answer is drawn from files other than the one in hand — read
     * on a receiver resolved to a codebase. A caller of any of these is reading the world.
     */
    private const array CROSS_FILE = [
        'declarationMatch', 'declarations', 'classNamed', 'ancestorsOf', 'extends', 'implements',
        'isEnum', 'isValueType', 'hasSubclass', 'usersOfTrait', 'methodReturnsNullable',
        'index', 'callersOf', 'valueFlow', 'projection', 'files', 'sourceOf',
    ];

    /**
     * How far a reference chain is followed. A reach longer than this is a class calling a class
     * calling a class; the fixed point is reached long before, and the bound keeps a cycle finite.
     */
    private const int DEPTH = 8;

    /**
     * @param  array<string, true>  $reaching  FQCN => it reaches a cross-file question
     * @param  array<string, true>  $known  every FQCN the read source declares
     */
    private function __construct(
        private readonly array $reaching,
        private readonly array $known,
    ) {}

    /**
     * Read $source — the engine's own classes plus every detector's, shipped or custom — and work
     * out which of them reach a cross-file question.
     */
    public static function over(Codebase $source): self
    {
        $reaching = self::askingDirectly($source);
        $references = self::referenceGraph($source);

        for ($step = 0; $step < self::DEPTH; $step++) {
            $before = count($reaching);

            foreach ($references as $from => $targets) {
                foreach ($targets as $target => $_) {
                    if (isset($reaching[$target])) {
                        $reaching[$from] = true;
                    }
                }
            }

            if (count($reaching) === $before) {
                break; // fixed point: nothing new is reachable
            }
        }

        return new self($reaching, array_map(static fn () => true, $source->declarations()));
    }

    /**
     * Does $detector read beyond the file it is judging? Evidence and declaration BOTH count: what
     * the source shows it reaching, plus what it declares of itself ({@see WholeTree}). A detector
     * the source never declared is not PROVEN local, only unseen, and counts as reading the world.
     */
    public function has(Detector $detector): bool
    {
        return $detector instanceof WholeTree
            || isset($this->reaching[$detector::class])
            || ! isset($this->known[$detector::class]);
    }

    /**
     * @return array<string, true>  the classes that put the question to the codebase themselves
     */
    private static function askingDirectly(Codebase $source): array
    {
        $asking = [];

        foreach (self::CROSS_FILE as $method) {
            // On a RECEIVER resolved to a codebase, never on the bare name: `files()` and `index()`
            // are words half the engine uses, and matching them by spelling taints everything.
            foreach ($source->whereMethod($method)->isUsedOn(Codebase::class)->get() as $call) {
                $asking[$call->enclosingClassName() ?? ''] = true;
            }
        }

        // The codebase asking ITSELF is the origin, not a reach through it — every one of these
        // methods is implemented in terms of the others, and counting that would taint the medium.
        unset($asking[''], $asking[Codebase::class]);

        return $asking;
    }

    /**
     * Which class names each class reaches for — what it constructs and what it calls statically.
     * HOLDING a codebase is not reaching through one: every detector is handed one, so a param type
     * would make the whole catalogue read the world and the question would answer itself.
     *
     * @return array<string, array<string, true>>  FQCN => the FQCNs it references
     */
    private static function referenceGraph(Codebase $source): array
    {
        $edges = [];

        foreach ($source->whereNew()->get() as $new) {
            self::link($edges, $new->enclosingClassName(), $new->newClassName());
        }

        foreach ($source->whereStaticCall()->get() as $call) {
            self::link($edges, $call->enclosingClassName(), $call->staticCallClass());
        }

        return $edges;
    }

    /**
     * @param  array<string, array<string, true>>  $edges
     */
    private static function link(array &$edges, ?string $from, ?string $target): void
    {
        if ($from === null || $target === null || $from === $target) {
            return;
        }

        $edges[$from][ltrim($target, '\\')] = true;
    }
}
