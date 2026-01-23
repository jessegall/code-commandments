<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\Pipes\MatchResult;
use JesseGall\CodeCommandments\Support\Pipes\Vue\VueContext;
use JesseGall\CodeCommandments\Support\Pipes\Vue\VuePipeline;

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

        return VuePipeline::make($filePath, $content)
            ->extractScript()
            ->returnRighteousIfNoScript()
            ->pipe(fn (VueContext $ctx) => $ctx->with(matches: $this->findViolations($ctx)))
            ->forEachMatch(function (MatchResult $match, VuePipeline $pipeline) {
                return $pipeline->sinAt(
                    $match->offset,
                    $match->groups['message'],
                    $pipeline->getSnippet($match->offset, 60),
                    'Use { item, disabled: !condition } with .filter()'
                );
            })
            ->judge();
    }

    private function findViolations(VueContext $ctx): array
    {
        $scriptContent = $ctx->getSectionContent();
        $matches = [];

        // Look for conditional spread patterns
        preg_match_all('/\.\.\..*\?.*\[/', $scriptContent, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($found as $match) {
            $matches[] = new MatchResult(
                name: 'conditional_spread',
                pattern: '',
                match: $match[0][0],
                line: $ctx->getLineFromOffset($match[0][1]),
                offset: $match[0][1],
                content: null,
                groups: ['message' => 'Conditional spread pattern - consider disabled flags pattern'],
            );
        }

        // Look for conditional .push() patterns
        $conditionalPushPatterns = [
            '/if\s*\([^)]+\)\s*\{?\s*\n?\s*(\w+)\.push\s*\(/m',
            '/if\s*\([^)]+\)\s+(\w+)\.push\s*\(/m',
            '/\w+\s*&&\s*(\w+)\.push\s*\(/m',
        ];

        foreach ($conditionalPushPatterns as $pattern) {
            preg_match_all($pattern, $scriptContent, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
            foreach ($found as $match) {
                if (!$this->isGroupingPattern($scriptContent, $match[0][1])) {
                    $matches[] = new MatchResult(
                        name: 'conditional_push',
                        pattern: $pattern,
                        match: $match[0][0],
                        line: $ctx->getLineFromOffset($match[0][1]),
                        offset: $match[0][1],
                        content: null,
                        groups: ['message' => 'Conditional array.push() - consider disabled flags pattern'],
                    );
                }
            }
        }

        return $matches;
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
