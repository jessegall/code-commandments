<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes\Php;

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

        foreach ($lines as $lineNum => $line) {
            foreach ($this->patterns as $name => $pattern) {
                if (preg_match($pattern, $line, $m)) {
                    $matches[] = [
                        'name' => $name,
                        'pattern' => $pattern,
                        'line' => $lineNum + 1,
                        'content' => trim($line),
                        'match' => $m[0],
                        'groups' => $m,
                    ];
                }
            }
        }

        // Store matches in a generic way - we'll add a matches property to PhpContext
        return $input->with(matches: $matches);
    }
}
