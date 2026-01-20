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

        // Look for .push() calls (potential conditional array building)
        $pushPattern = '/\.push\s*\(/';
        preg_match_all($pushPattern, $scriptContent, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($matches as $match) {
            $offset = $match[0][1];
            $line = $this->getLineFromOffset($content, $scriptStart + $offset);

            $sins[] = $this->sinAt(
                $line,
                'Array .push() call - consider disabled flags pattern if conditional',
                $this->getSnippet($scriptContent, $offset, 60),
                'Use { item, disabled: !condition } with .filter()'
            );
        }

        return empty($sins) ? $this->righteous() : $this->fallen($sins);
    }
}
