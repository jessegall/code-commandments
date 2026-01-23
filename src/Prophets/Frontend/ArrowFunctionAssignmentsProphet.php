<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\Pipes\MatchResult;
use JesseGall\CodeCommandments\Support\Pipes\Vue\VueContext;
use JesseGall\CodeCommandments\Support\Pipes\Vue\VuePipeline;

/**
 * Prefer named function declarations over arrow function assignments.
 *
 * Use named function declarations instead of arrow function assignments
 * for better stack traces and code readability.
 */
class ArrowFunctionAssignmentsProphet extends FrontendCommandment
{
    public function applicableExtensions(): array
    {
        return ['vue'];
    }

    public function description(): string
    {
        return 'Prefer named function declarations over arrow function assignments';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Use named function declarations instead of arrow function assignments
for better stack traces and code readability.

Bad:
    const handleClick = () => {
        // ...
    };

    const fetchData = async () => {
        // ...
    };

Good:
    function handleClick() {
        // ...
    }

    async function fetchData() {
        // ...
    }

Exceptions (arrow functions are fine for):
- Inline callbacks: array.map(item => item.id)
- Short one-liners: const double = (n) => n * 2;
- Computed properties: const fullName = computed(() => ...)
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        if ($this->shouldSkipExtension($filePath)) {
            return $this->righteous();
        }

        return VuePipeline::make($filePath, $content)
            ->extractScript()
            ->returnRighteousIfNoScript()
            ->pipe(fn (VueContext $ctx) => $ctx->with(matches: $this->findArrowAssignments($ctx)))
            ->forEachMatch(function (MatchResult $match, VuePipeline $pipeline) {
                $name = $match->groups['name'];

                return $pipeline->sinAt(
                    $match->offset,
                    "Arrow function assignment for '{$name}' - prefer named function declaration",
                    $pipeline->getSnippet($match->offset, 60),
                    "Use: function {$name}() { ... } or async function {$name}() { ... }"
                );
            })
            ->judge();
    }

    private function findArrowAssignments(VueContext $ctx): array
    {
        $scriptContent = $ctx->getSectionContent();
        $matches = [];
        $pattern = '/const\s+(\w+)\s*=\s*(?:async\s*)?\([^)]*\)\s*=>\s*\{/';

        preg_match_all($pattern, $scriptContent, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($found as $match) {
            $fullMatch = $match[0][0];
            $functionName = $match[1][0];
            $offset = $match[0][1];

            if (str_contains($fullMatch, 'computed(')) {
                continue;
            }

            $beforeMatch = substr($scriptContent, max(0, $offset - 20), 20);
            if (str_contains($beforeMatch, 'computed(')) {
                continue;
            }

            $matches[] = new MatchResult(
                name: 'arrow_assignment',
                pattern: $pattern,
                match: $fullMatch,
                line: $ctx->getLineFromOffset($offset),
                offset: $offset,
                content: $fullMatch,
                groups: ['name' => $functionName],
            );
        }

        return $matches;
    }
}
