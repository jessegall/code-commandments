<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use JesseGall\CodeCommandments\Contracts\Commandment;
use JesseGall\CodeCommandments\Contracts\ConfessionTracker;
use JesseGall\CodeCommandments\Results\Finding;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\PhpTypes\T_String;

/**
 * Flattens the per-file / per-prophet judgment structure into an ordered
 * list of individual Findings — applying finding-level absolution and
 * marking every live fingerprint as seen so stale absolutions can be
 * garbage-collected.
 *
 * This is the single source of truth shared by the compact judge output,
 * the serialized `--next` walk, and (for completeness) any future
 * presentation, so the three never drift apart.
 */
final class FindingCollector
{
    /** @var array<string, Commandment> */
    private array $prophets = [];

    public function __construct(
        private readonly ConfessionTracker $tracker,
    ) {}

    /**
     * @param  iterable<string, iterable<string, Judgment>>  $results  path => (prophetClass => Judgment)
     * @return list<Finding>
     */
    public function collect(iterable $results, ?string $prophetFilter = null, bool $markSeen = true): array
    {
        $findings = [];

        foreach ($results as $filePath => $judgments) {
            $relativePath = $this->relative($filePath);

            foreach ($judgments as $prophetClass => $judgment) {
                if ($prophetFilter !== null && ! $this->matchesFilter($prophetClass, $prophetFilter)) {
                    continue;
                }

                if ($this->isFileAbsolved($filePath, $prophetClass)) {
                    continue;
                }

                $prophet = $this->prophet($prophetClass);
                $tier = $prophet->tier();
                $advisory = $prophet->advisory();
                $supersedes = $prophet->supersedes();
                $repairable = $prophet instanceof \JesseGall\CodeCommandments\Contracts\SinRepenter;
                $short = class_basename($prophetClass);

                foreach ($judgment->sins as $sin) {
                    $fingerprint = Fingerprint::of($prophetClass, $relativePath, $sin->symbol, $sin->snippet);

                    if ($markSeen) {
                        $this->tracker->markFindingSeen($fingerprint);
                    }

                    if ($this->tracker->isFindingAbsolved($fingerprint)) {
                        continue;
                    }

                    $findings[] = new Finding(
                        prophetClass: $prophetClass,
                        prophetShort: $short,
                        filePath: $filePath,
                        relativePath: $relativePath,
                        kind: 'sin',
                        line: $sin->line,
                        message: $sin->message,
                        snippet: $sin->snippet,
                        suggestion: $sin->suggestion,
                        symbol: $sin->symbol,
                        advisory: $advisory,
                        tier: $tier,
                        supersedes: $supersedes,
                        fingerprint: $fingerprint,
                        autoFixable: $sin->autoFixable ?? $repairable,
                    );
                }

                foreach ($judgment->warnings as $warning) {
                    $fingerprint = Fingerprint::of($prophetClass, $relativePath, $warning->symbol, $warning->snippet);

                    if ($markSeen) {
                        $this->tracker->markFindingSeen($fingerprint);
                    }

                    if ($this->tracker->isFindingAbsolved($fingerprint)) {
                        continue;
                    }

                    $findings[] = new Finding(
                        prophetClass: $prophetClass,
                        prophetShort: $short,
                        filePath: $filePath,
                        relativePath: $relativePath,
                        kind: 'warning',
                        line: $warning->line,
                        message: $warning->message,
                        snippet: $warning->snippet,
                        suggestion: null,
                        symbol: $warning->symbol,
                        advisory: $advisory,
                        tier: $tier,
                        supersedes: $supersedes,
                        fingerprint: $fingerprint,
                        autoFixable: $warning->autoFixable ?? $repairable,
                    );
                }
            }
        }

        return $findings;
    }

    /**
     * Collect and order findings for one-at-a-time resolution.
     *
     * @param  iterable<string, iterable<string, Judgment>>  $results
     * @return list<Finding>
     */
    public function ordered(iterable $results, ?string $prophetFilter = null, bool $markSeen = true): array
    {
        return FindingQueue::order($this->collect($results, $prophetFilter, $markSeen));
    }

    private function prophet(string $prophetClass): Commandment
    {
        return $this->prophets[$prophetClass] ??= new $prophetClass();
    }

    private function matchesFilter(string $prophetClass, string $filter): bool
    {
        return str_contains(strtolower(class_basename($prophetClass)), strtolower($filter));
    }

    private function relative(string $filePath): string
    {
        return str_replace(Environment::basePath() . '/', T_String::empty(), $filePath);
    }

    private function isFileAbsolved(string $filePath, string $prophetClass): bool
    {
        if (! $this->tracker->isAbsolved($filePath, $prophetClass)) {
            return false;
        }

        $content = @file_get_contents($filePath);

        return $content !== false && ! $this->tracker->hasChangedSinceAbsolution($filePath, $prophetClass, $content);
    }
}
