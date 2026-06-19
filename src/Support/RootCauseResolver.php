<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use JesseGall\CodeCommandments\Contracts\NeedsCodebaseIndex;
use JesseGall\CodeCommandments\Results\Finding;
use JesseGall\CodeCommandments\Results\RootCauseHint;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use Throwable;

/**
 * Symptom-side root-cause trigger. For a symptom finding whose root-cause
 * prophet was filtered OUT of the run (so `supersedes` deferral can't fire), it
 * runs the declared cause prophet(s) on the same file/region and, if one fires,
 * annotates the symptom with a {@see RootCauseHint} — "fix the cause first; do
 * not launder it". If none fires it marks the finding "checked" (a confirmed
 * genuine absence — the symptom's own suggestion is correct).
 *
 * Depth-1 (a triggered cause never triggers its own causes), region-scoped to
 * {@see FindingQueue::DEFER_WINDOW}, and side-effect-free: it never marks
 * fingerprints seen, touches the findings cache, or adds cause findings to the
 * presented set — only the hint VO — so `--prophet=` filtered runs stay clean.
 *
 * Resolve LAZILY: callers annotate only the finding(s) actually presented, so a
 * full `judge --next` pays no trigger cost when no symptom is shown.
 */
final class RootCauseResolver
{
    /** @var array<string, list<int|null>> memo: "cause\0file" => cause finding lines */
    private array $causeLinesCache = [];

    /** @var array<string, ?string> memo: file => content */
    private array $contentCache = [];

    /**
     * @param  callable(string): ?CodebaseIndex  $indexProvider  file path => index (lazy)
     */
    public function __construct(
        private $indexProvider,
    ) {}

    /**
     * @param  array<class-string, true>  $activeProphets  prophets that actually ran
     */
    public function annotate(Finding $finding, array $activeProphets): Finding
    {
        if ($finding->rootCauses === []) {
            return $finding;
        }

        $inactive = [];

        foreach ($finding->rootCauses as $cause) {
            if (! isset($activeProphets[$cause])) {
                $inactive[] = $cause;
            }
        }

        // All causes ran (full run / unfiltered): `supersedes` deferral in
        // FindingQueue already handles ordering — no trigger, no hint.
        if ($inactive === []) {
            return $finding;
        }

        foreach ($this->cheapestFirst($inactive) as $cause) {
            if ($this->causeFiresInRegion($cause, $finding)) {
                return $finding->withRootCauseHint(
                    new RootCauseHint(class_basename($cause), $cause, self::reasonFor($cause)),
                    true,
                );
            }
        }

        // Checked, nothing matched → genuine absence: the symptom is correct.
        return $finding->withRootCauseHint(null, true);
    }

    private function causeFiresInRegion(string $cause, Finding $finding): bool
    {
        foreach ($this->causeLines($cause, $finding->filePath) as $line) {
            if ($this->inRegion($finding->line, $line)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Lines of every finding the cause prophet produces on this file (memoized
     * per cause+file). Index-needing causes get the file's scroll index.
     *
     * @return list<int|null>
     */
    private function causeLines(string $cause, string $filePath): array
    {
        $key = $cause . "\0" . $filePath;

        if (isset($this->causeLinesCache[$key])) {
            return $this->causeLinesCache[$key];
        }

        $lines = [];

        if (class_exists($cause)) {
            $content = $this->content($filePath);

            if ($content !== null) {
                try {
                    $prophet = new $cause();

                    if ($prophet instanceof NeedsCodebaseIndex) {
                        $index = ($this->indexProvider)($filePath);

                        if ($index instanceof CodebaseIndex) {
                            $prophet->setCodebaseIndex($index);
                        }
                    }

                    $judgment = $prophet->judge($filePath, $content);

                    foreach ($judgment->sins as $sin) {
                        $lines[] = $sin->line;
                    }

                    foreach ($judgment->warnings as $warning) {
                        $lines[] = $warning->line;
                    }
                } catch (Throwable) {
                    $lines = [];
                }
            }
        }

        return $this->causeLinesCache[$key] = $lines;
    }

    private function inRegion(?int $a, ?int $b): bool
    {
        if ($a === null || $b === null) {
            return true;
        }

        return abs($a - $b) <= FindingQueue::DEFER_WINDOW;
    }

    /**
     * Index-free causes first (enum dispatch / swallowed not-found) so the
     * common single-file match short-circuits before any index-needing cause
     * (registry / total) has to be run.
     *
     * @param  list<class-string>  $causes
     * @return list<class-string>
     */
    private function cheapestFirst(array $causes): array
    {
        usort($causes, static fn (string $a, string $b): int =>
            (self::needsIndex($a) ? 1 : 0) <=> (self::needsIndex($b) ? 1 : 0));

        return $causes;
    }

    private static function needsIndex(string $cause): bool
    {
        return class_exists($cause) && is_a($cause, NeedsCodebaseIndex::class, true);
    }

    private function content(string $filePath): ?string
    {
        if (array_key_exists($filePath, $this->contentCache)) {
            return $this->contentCache[$filePath];
        }

        $content = @file_get_contents($filePath);

        return $this->contentCache[$filePath] = ($content === false ? null : $content);
    }

    private static function reasonFor(string $cause): string
    {
        return match (class_basename($cause)) {
            'ThrowOnUnhandledCaseProphet' => 'an unhandled closed-set case is returning null — make the match total or throw at the source, do not model the bug as absence',
            'RegistryReturnContractProphet' => 'a registry miss is a wiring bug — return the item or throw (with a has() companion), not Option/null',
            'PreferTotalOverNullableProphet' => 'every caller already de-nulls this — make it total (a Null Object / exhaustive match) or throw at the source',
            'NoSwallowedNotFoundProphet' => 'a not-found exception is being swallowed into a sentinel — let it throw instead of degrading to a default',
            default => 'this absence looks like an invariant violation, not a genuine absence',
        };
    }
}
