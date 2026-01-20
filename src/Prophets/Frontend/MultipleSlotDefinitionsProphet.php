<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Results\Judgment;

/**
 * Components with multiple slots should have proper documentation.
 *
 * Components that define 3+ slots should document their slot API
 * to help consumers understand how to use the component correctly.
 */
class MultipleSlotDefinitionsProphet extends FrontendCommandment
{
    private const SLOT_THRESHOLD = 3;

    public function applicableExtensions(): array
    {
        return ['vue'];
    }

    public function description(): string
    {
        return 'Components with multiple slots should have proper documentation';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Components that define 3+ slots should document their slot API.
This helps consumers understand how to use the component correctly.

When you have multiple slots:
1. Consider if all slots are necessary
2. Document each slot's purpose
3. Consider using named slots for clarity

Example documentation:
    /**
     * Card component with header, content, and footer slots
     *
     * @slot header - Card header content
     * @slot default - Main card content
     * @slot footer - Card footer with actions
     */
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        if ($this->shouldSkipExtension($filePath)) {
            return $this->righteous();
        }

        // Only check component files (in /components/ or /Components/ directories)
        if (!str_contains($filePath, '/components/') && !str_contains($filePath, '/Components/')) {
            return $this->righteous();
        }

        $script = $this->extractScript($content);

        if ($script === null) {
            return $this->skip('No script section found');
        }

        $scriptContent = $script['content'];

        // Look for defineSlots usage and count slot definitions
        if (!preg_match('/defineSlots\s*<\s*\{([^}]*)\}\s*>/', $scriptContent, $matches)) {
            return $this->righteous();
        }

        // Count the slot definitions (each slot is a key in the type definition)
        $slotDefinitions = $matches[1];
        $slotCount = preg_match_all('/(\w+)\s*[:?]\s*/', $slotDefinitions);

        if ($slotCount >= self::SLOT_THRESHOLD) {
            $line = $this->getLineFromOffset($content, $script['start']);

            return $this->fallen([
                $this->sinAt(
                    $line,
                    "Defines {$slotCount} slots - ensure proper documentation",
                    null,
                    'Add JSDoc documentation for each slot with @slot tags'
                ),
            ]);
        }

        return $this->righteous();
    }
}
