<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\Pipes\Vue\VueContext;
use JesseGall\CodeCommandments\Support\Pipes\Vue\VuePipeline;

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

        return VuePipeline::make($filePath, $content)
            ->onlyPageFiles()
            ->excludePartialFiles()
            ->returnRighteousWhen(fn (VueContext $ctx) => (bool) preg_match('/(Dialog|Modal|Sheet|Drawer)\.vue$/', $ctx->filePath))
            ->inTemplate()
            ->mapToWarnings(function (VueContext $ctx) {
                $templateContent = $ctx->getSectionContent();
                $dialogCount = preg_match_all('/<(Dialog|Modal|Sheet|Drawer)\b/', $templateContent);

                if ($dialogCount > 0) {
                    // Check if the dialog has substantial content (more than just a simple confirmation)
                    $minContentLength = (int) $this->config('min_content_length', 200);
                    if (preg_match('/<(Dialog|Modal|Sheet|Drawer)[^>]*>[\s\S]{'.$minContentLength.',}?<\/(Dialog|Modal|Sheet|Drawer)>/s', $templateContent)) {
                        return $this->warningAt(
                            1,
                            "Contains {$dialogCount} inline dialog(s) - consider extracting to components",
                            'Extract dialogs to dedicated component files'
                        );
                    }
                }

                return null;
            })
            ->judge();
    }
}
