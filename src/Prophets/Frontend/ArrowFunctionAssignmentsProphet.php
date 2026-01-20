<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Results\Judgment;

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

        $script = $this->extractScript($content);

        if ($script === null) {
            return $this->skip('No script section found');
        }

        $scriptContent = $script['content'];
        $scriptStart = $script['start'];
        $sins = [];

        // Look for const name = () => { patterns (multi-line arrow functions)
        // This regex looks for arrow functions assigned to const that span multiple lines
        $pattern = '/const\s+(\w+)\s*=\s*(?:async\s*)?\([^)]*\)\s*=>\s*\{/';

        preg_match_all($pattern, $scriptContent, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($matches as $match) {
            $fullMatch = $match[0][0];
            $functionName = $match[1][0];
            $offset = $match[0][1];

            // Skip if it's a computed property
            if (str_contains($fullMatch, 'computed(')) {
                continue;
            }

            // Check if this is inside a computed() call by looking at surrounding context
            $beforeMatch = substr($scriptContent, max(0, $offset - 20), 20);
            if (str_contains($beforeMatch, 'computed(')) {
                continue;
            }

            $line = $this->getLineFromOffset($content, $scriptStart + $offset);

            $sins[] = $this->sinAt(
                $line,
                "Arrow function assignment for '{$functionName}' - prefer named function declaration",
                $this->getSnippet($scriptContent, $offset, 60),
                "Use: function {$functionName}() { ... } or async function {$functionName}() { ... }"
            );
        }

        return empty($sins) ? $this->righteous() : $this->fallen($sins);
    }
}
