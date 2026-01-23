<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Results\Judgment;

/**
 * Consider disabled flags pattern instead of conditional array building.
 *
 * Instead of conditionally building arrays with .push() or spread conditionals,
 * consider using the disabled flags pattern.
 */
class ConditionalArrayBuildingProphet extends FrontendCommandment
{
    public function applicableExtensions(): array
    {
        return ['vue'];
    }

    public function description(): string
    {
        return 'Consider disabled flags pattern instead of conditional array building';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Instead of conditionally building arrays with .push() or spread conditionals,
consider using the disabled flags pattern.

Bad:
    const actions = [];
    if (canEdit) actions.push({ label: 'Edit', ... });
    if (canDelete) actions.push({ label: 'Delete', ... });

    // Or:
    const actions = [
        ...(canEdit ? [{ label: 'Edit' }] : []),
        ...(canDelete ? [{ label: 'Delete' }] : []),
    ];

Good:
    const actions = [
        { label: 'Edit', disabled: !canEdit, ... },
        { label: 'Delete', disabled: !canDelete, ... },
    ].filter(a => !a.disabled);

    // Or for UI:
    const actions = [
        { label: 'Edit', show: canEdit, ... },
        { label: 'Delete', show: canDelete, ... },
    ];
    // Then filter in template or use v-if on show property

Note: Grouping operations (obj[key].push()) inside loops are NOT flagged,
as they serve a different purpose (categorizing items by key).
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

        // Look for conditional spread patterns: ...( condition ? [...] : [] )
        $spreadPattern = '/\.\.\..*\?.*\[/';
        preg_match_all($spreadPattern, $scriptContent, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($matches as $match) {
            $offset = $match[0][1];
            $line = $this->getLineFromOffset($content, $scriptStart + $offset);

            $sins[] = $this->sinAt(
                $line,
                'Conditional spread pattern - consider disabled flags pattern',
                $this->getSnippet($scriptContent, $offset, 60),
                'Use { item, disabled: !condition } with .filter()'
            );
        }

        // Look for conditional .push() patterns: if (...) array.push() or condition && array.push()
        // This excludes grouping patterns like: obj[key].push() inside loops
        $conditionalPushPatterns = [
            // if (condition) array.push(...) - simple array name before .push
            '/if\s*\([^)]+\)\s*\{?\s*\n?\s*(\w+)\.push\s*\(/m',
            // if (condition) array.push(...) - single line without braces
            '/if\s*\([^)]+\)\s+(\w+)\.push\s*\(/m',
            // condition && array.push(...)
            '/\w+\s*&&\s*(\w+)\.push\s*\(/m',
        ];

        foreach ($conditionalPushPatterns as $pattern) {
            preg_match_all($pattern, $scriptContent, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

            foreach ($matches as $match) {
                $offset = $match[0][1];
                $fullMatch = $match[0][0];

                // Skip if this looks like a grouping pattern (dynamic key access before .push)
                if ($this->isGroupingPattern($scriptContent, $offset)) {
                    continue;
                }

                $line = $this->getLineFromOffset($content, $scriptStart + $offset);

                $sins[] = $this->sinAt(
                    $line,
                    'Conditional array.push() - consider disabled flags pattern',
                    $this->getSnippet($scriptContent, $offset, 60),
                    'Use { item, disabled: !condition } with .filter()'
                );
            }
        }

        return empty($sins) ? $this->righteous() : $this->fallen($sins);
    }

    /**
     * Check if a .push() call is part of a grouping pattern.
     *
     * Grouping patterns typically:
     * - Use dynamic key access: obj[key].push()
     * - Are inside a loop (for, forEach, etc.)
     * - Push to categorize items, not conditionally include them
     */
    private function isGroupingPattern(string $content, int $offset): bool
    {
        // Look at context around the match (200 chars before)
        $contextStart = max(0, $offset - 200);
        $context = substr($content, $contextStart, $offset - $contextStart + 50);

        // Check if there's a dynamic key access pattern nearby: obj[key].push or obj[variable].push
        // This indicates grouping: grouped[parentId].push(), byCategory[key].push(), etc.
        if (preg_match('/\w+\[\w+\]\.push/', $context)) {
            return true;
        }

        // Check if we're inside a for loop (common for grouping)
        // Look for: for (... of ...) or for (... in ...) or forEach
        if (preg_match('/for\s*\([^)]*\b(of|in)\b[^)]*\)\s*\{[^}]*$/s', $context)) {
            return true;
        }

        if (preg_match('/\.forEach\s*\([^)]*\)\s*\{[^}]*$/s', $context)) {
            return true;
        }

        return false;
    }
}
