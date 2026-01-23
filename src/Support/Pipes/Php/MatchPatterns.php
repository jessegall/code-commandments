<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes\Php;

use JesseGall\CodeCommandments\Support\Pipes\MatchResult;
use JesseGall\CodeCommandments\Support\Pipes\Pipe;

/**
 * Match regex patterns in the content and store matches in context.
 *
 * @implements Pipe<PhpContext, PhpContext>
 */
final class MatchPatterns implements Pipe
{
    /** @var array<string, string> */
    private array $patterns = [];

    /**
     * Add a pattern to match.
     *
     * @param  string  $name  Identifier for the pattern
     * @param  string  $pattern  Regex pattern
     */
    public function add(string $name, string $pattern): self
    {
        $this->patterns[$name] = $pattern;

        return $this;
    }

    public function handle(mixed $input): mixed
    {
        $matches = [];
        $lines = explode("\n", $input->content);
        $offset = 0;

        foreach ($lines as $lineNum => $line) {
            foreach ($this->patterns as $name => $pattern) {
                if (preg_match($pattern, $line, $m, PREG_OFFSET_CAPTURE)) {
                    $matches[] = new MatchResult(
                        name: $name,
                        pattern: $pattern,
                        match: $m[0][0],
                        line: $lineNum + 1,
                        offset: $offset + $m[0][1],
                        content: trim($line),
                        groups: array_map(fn ($g) => $g[0], $m),
                    );
                }
            }
            $offset += strlen($line) + 1; // +1 for newline
        }

        return $input->with(matches: $matches);
    }
}
