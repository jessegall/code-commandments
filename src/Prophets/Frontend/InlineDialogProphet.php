<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Results\Judgment;

/**
 * Extract inline dialog definitions to separate components.
 *
 * Pages should not have inline dialog/modal definitions. Extract them
 * to dedicated components for better maintainability and reusability.
 */
class InlineDialogProphet extends FrontendCommandment
{
    public function applicableExtensions(): array
    {
        return ['vue'];
    }

    public function requiresConfession(): bool
    {
        return true;
    }

    public function description(): string
    {
        return 'Extract inline dialog definitions to separate components';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Pages should not have inline dialog/modal definitions. Extract them
to dedicated components for better maintainability and reusability.

Bad:
    <!-- In Pages/Products/Index.vue -->
    <Dialog v-model="showEditDialog">
        <form>
            <!-- 50+ lines of dialog content -->
        </form>
    </Dialog>

Good:
    <!-- In Pages/Products/Index.vue -->
    <EditProductDialog
        v-model="showEditDialog"
        :product="selectedProduct"
    />

    <!-- In Components/Products/EditProductDialog.vue -->
    <Dialog v-model="modelValue">
        <form>...</form>
    </Dialog>

Benefits:
- Keeps page components focused on layout
- Dialogs become reusable
- Easier to test dialog logic in isolation
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        if ($this->shouldSkipExtension($filePath)) {
            return $this->righteous();
        }

        // Only check page files
        if (!str_contains($filePath, '/Pages/') && !str_contains($filePath, '/pages/')) {
            return $this->righteous();
        }

        $template = $this->extractTemplate($content);

        if ($template === null) {
            return $this->skip('No template section found');
        }

        $templateContent = $template['content'];

        // Count Dialog/Modal components in the file
        $dialogCount = preg_match_all('/<(Dialog|Modal|Sheet|Drawer)\b/', $templateContent);

        if ($dialogCount > 0) {
            // Check if the dialog has substantial content (more than just a simple confirmation)
            // Look for dialogs with form elements or significant content (200+ chars)
            if (preg_match('/<(Dialog|Modal|Sheet|Drawer)[^>]*>[\s\S]{200,}?<\/(Dialog|Modal|Sheet|Drawer)>/s', $templateContent)) {
                return Judgment::withWarnings([
                    $this->warningAt(
                        1,
                        "Contains {$dialogCount} inline dialog(s) - consider extracting to components",
                        'Extract dialogs to dedicated component files'
                    ),
                ]);
            }
        }

        return $this->righteous();
    }
}
