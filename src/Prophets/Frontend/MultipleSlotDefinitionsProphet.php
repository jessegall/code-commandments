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

        $template = $this->extractTemplate($content);

        if ($template === null) {
            return $this->skip('No template section found');
        }

        $templateContent = $template['content'];

        // Count slot definitions
        $slotCount = preg_match_all('/<slot/', $templateContent);

        if ($slotCount >= self::SLOT_THRESHOLD) {
            return $this->fallen([
                $this->sinAt(
                    1,
                    "Defines {$slotCount} slots - ensure proper documentation",
                    null,
                    'Add JSDoc documentation for each slot with @slot tags'
                ),
            ]);
        }

        return $this->righteous();
    }
}
