<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes\Vue;

use JesseGall\CodeCommandments\Support\Pipes\MatchResult;
use JesseGall\CodeCommandments\Support\Pipes\Pipe;

/**
 * Match regex patterns against the current Vue section content.
 *
 * @implements Pipe<VueContext, VueContext>
 */
final class MatchPatterns implements Pipe
{
    /** @var array<string, string> */
    private array $patterns = [];

    /**
     * Add a named pattern to match.
     */
    public function add(string $name, string $pattern): self
    {
        $this->patterns[$name] = $pattern;

        return $this;
    }

    public function handle(mixed $input): mixed
    {
        $sectionContent = $input->getSectionContent();

        if ($sectionContent === null) {
            return $input;
        }

        $matches = [];

        foreach ($this->patterns as $name => $pattern) {
            preg_match_all($pattern, $sectionContent, $rawMatches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

            foreach ($rawMatches as $match) {
                $offset = $match[0][1];
                $matchStr = $match[0][0];

                // Extract groups (skip the full match)
                $groups = [];
                foreach ($match as $i => $group) {
                    if ($i > 0) {
                        $groups[$i] = $group[0];
                    }
                }

                $matches[] = new MatchResult(
                    name: $name,
                    pattern: $pattern,
                    match: $matchStr,
                    line: $input->getLineFromOffset($offset),
                    offset: $offset,
                    content: $input->getSnippet($offset),
                    groups: $groups,
                );
            }
        }

        return $input->with(matches: $matches);
    }
}
