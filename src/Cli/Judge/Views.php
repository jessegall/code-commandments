<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Judge;

use JesseGall\CodeCommandments\Cli\Scope\Scope;
use JesseGall\CodeCommandments\Codebase;
use JesseGall\CodeCommandments\Detector;
use JesseGall\CodeCommandments\Detectors\CrossFileSet;

/**
 * WHICH codebase each rule is judged against. An unscoped run shows every rule the whole tree; a
 * scoped one (`--changes`, `--branch`) still parses the tree, but shows it only to the rules that
 * READ beyond one file ({@see CrossFileSet}) and every other rule the scoped files alone
 * ({@see Codebase::focusedOn}), so their cost tracks the diff. Engine-agnostic: the Vue side narrows
 * through the same base type.
 *
 * @template T of Codebase
 */
final class Views
{
    /**
     * @param  T  $whole
     * @param  T|null  $focused  null when the run is not scoped — then every rule sees $whole
     */
    private function __construct(
        private readonly Codebase $whole,
        private readonly ?Codebase $focused,
        private readonly CrossFileSet $beyond,
    ) {}

    /**
     * Every rule sees the whole of $codebase — a run with nothing to narrow.
     *
     * @template TWhole of Codebase
     *
     * @param  TWhole  $codebase
     * @return self<TWhole>
     */
    public static function whole(Codebase $codebase): self
    {
        return new self($codebase, null, CrossFileSet::unread());
    }

    /**
     * The views for one run of $codebase under $scope: an unscoped scope narrows nothing, and a
     * scoped one narrows for every rule that reads no further than the file it judges.
     *
     * @template TCodebase of Codebase
     *
     * @param  TCodebase  $codebase
     * @return self<TCodebase>
     */
    public static function of(Codebase $codebase, Scope $scope, CrossFileSet $beyond): self
    {
        $files = $scope->files();

        if ($files === null) {
            return new self($codebase, null, $beyond);
        }

        // The scope's own restrictions decide too: a frozen or excluded file is never reported on,
        // so a view of it would be a view of files nothing may flag.
        $judged = array_filter(array_keys($files), $scope->includes(...));

        return new self($codebase, $codebase->focusedOn(...$judged), $beyond);
    }

    /**
     * The codebase $detector is judged against — the scoped view when it reads no further than the
     * file in hand, the whole tree when it does.
     *
     * @return T
     */
    public function for(Detector $detector): Codebase
    {
        return $this->focused === null || $this->beyond->has($detector)
            ? $this->whole
            : $this->focused;
    }

    /**
     * The whole tree, when any of $detectors will be shown it — null when none will, so a caller can
     * skip building whole-program work no rule in this run is going to read.
     *
     * @param  list<Detector>  $detectors
     * @return T|null
     */
    public function wholeTreeFor(array $detectors): ?Codebase
    {
        return array_any($detectors, fn (Detector $detector): bool => $this->for($detector) === $this->whole)
            ? $this->whole
            : null;
    }
}
