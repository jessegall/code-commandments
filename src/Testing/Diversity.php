<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

/**
 * The coverage-diversity engine, shared by BOTH fixtures (backend and frontend):
 * the largest group of findings that are all pairwise DIVERSE — in different files
 * AND with less than `$maxSimilarity`% source overlap. A max-clique over the
 * diversity graph, so a finding that resembles everything can't mask a genuinely
 * diverse trio hiding behind it, and order doesn't change the answer.
 *
 * It is engine-agnostic on purpose: each fixture supplies `{file, source}` per
 * finding — what "the source of one scenario" is (a PHP class body, a Vue element's
 * markup) is the only thing that differs, and that's the caller's to decide.
 */
final class Diversity
{
    public const int MIN_SCENARIOS = 3;

    public const int MAX_SIMILARITY = 60;

    public function __construct(
        private readonly int $maxSimilarity = self::MAX_SIMILARITY,
    ) {}

    /**
     * @param  list<array{file: string, source: string}>  $findings
     */
    public function largestGroup(array $findings): int
    {
        $count = count($findings);

        if ($count === 0) {
            return 0;
        }

        $diverse = [];

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $diverse[$i][$j] = $diverse[$j][$i] = $findings[$i]['file'] !== $findings[$j]['file']
                    && $this->similarity($findings[$i]['source'], $findings[$j]['source']) < $this->maxSimilarity;
            }
        }

        return $this->growClique(range(0, $count - 1), 0, $diverse);
    }

    /**
     * @param  list<int>  $candidates  finding indices that may still extend the clique
     * @param  array<int, array<int, bool>>  $diverse
     */
    private function growClique(array $candidates, int $size, array $diverse): int
    {
        $best = $size;

        foreach ($candidates as $k => $node) {
            $rest = array_values(array_filter(
                array_slice($candidates, $k + 1),
                static fn (int $other): bool => $diverse[$node][$other] ?? false,
            ));

            $best = max($best, $this->growClique($rest, $size + 1, $diverse));
        }

        return $best;
    }

    private function similarity(string $a, string $b): float
    {
        similar_text($this->normalise($a), $this->normalise($b), $percent);

        return $percent;
    }

    private function normalise(string $code): string
    {
        return (string) preg_replace('/\s+/', ' ', trim($code));
    }
}
