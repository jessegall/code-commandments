<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\Pipes\Vue\VueContext;
use JesseGall\CodeCommandments\Support\Pipes\Vue\VuePipeline;

/**
 * Detect repeating patterns that could be extracted into reusable components or composables.
 *
 * This validator detects repeating patterns in Vue files that suggest extraction opportunities.
 */
class RepeatingPatternsProphet extends FrontendCommandment
{
    public function requiresConfession(): bool
    {
        return true;
    }

    public function applicableExtensions(): array
    {
        return ['vue'];
    }

    public function description(): string
    {
        return 'Detect repeating patterns that could be extracted into reusable components or composables';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
This validator detects repeating patterns in Vue files that suggest
extraction opportunities:

1. Dialog patterns: Multiple similar Dialog/AlertDialog usages
   → Extract into a reusable dialog component

2. Form field patterns: Multiple Input+Label+Error combinations
   → Extract into a FormField component

3. State management patterns: Multiple similar ref() declarations
   → Extract into a composable (e.g., useDialogState)

4. Handler patterns: Multiple similar open/close/onSaved functions
   → Extract into a composable

5. Section patterns: Repeated structural patterns in templates
   → Extract into section components

6. Similar v-model bindings: Multiple similar v-model patterns
   → Consider a more generic component

When patterns repeat 3+ times, extraction becomes worthwhile.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        if ($this->shouldSkipExtension($filePath)) {
            return $this->righteous();
        }

        return VuePipeline::make($filePath, $content)
            ->extractTemplate()
            ->extractScript()
            ->mapToWarnings(function (VueContext $ctx) {
                $templateContent = $ctx->template['content'] ?? '';
                $scriptContent = $ctx->script['content'] ?? '';

                $warnings = [];
                $detectors = [
                    'detectDialogPatterns',
                    'detectFormFieldPatterns',
                    'detectRefStatePatterns',
                    'detectHandlerPatterns',
                    'detectSectionPatterns',
                    'detectSimilarVModelPatterns',
                    'detectRepeatedClassPatterns',
                    'detectRepeatedComponentPatterns',
                ];

                foreach ($detectors as $method) {
                    $result = $this->$method($templateContent, $scriptContent);
                    if ($result) {
                        $warnings[] = $this->warningAt(1, $result, 'Consider extracting into reusable component or composable');
                    }
                }

                return $warnings;
            })
            ->judge();
    }

    /**
     * Detect multiple Dialog/AlertDialog usages that could be extracted.
     */
    private function detectDialogPatterns(string $template, string $script): ?string
    {
        $dialogCount = preg_match_all('/<(Alert)?Dialog\s/i', $template);
        $dialogOpenRefs = preg_match_all('/(\w+DialogOpen|dialog\w*Open)\s*=\s*ref\s*\(\s*false\s*\)/', $script);

        if ($dialogCount >= (int) $this->config('min_dialogs', 3)) {
            return "Found {$dialogCount} dialogs - consider extracting similar dialogs into a reusable component";
        }

        if ($dialogOpenRefs >= (int) $this->config('min_dialog_refs', 4) && $dialogCount === 0) {
            return "Found {$dialogOpenRefs} dialog state refs - consider extracting dialog management into a composable";
        }

        return null;
    }

    /**
     * Detect repeated form field patterns (Input + Label + error).
     */
    private function detectFormFieldPatterns(string $template, string $script): ?string
    {
        // Check for repeated Input patterns
        $inputCount = preg_match_all('/<Input\s[^>]*v-model[^>]*>/', $template);
        if ($inputCount >= (int) $this->config('min_input_fields', 5)) {
            return "Found {$inputCount} Input fields - consider extracting repeated field patterns";
        }

        return null;
    }

    /**
     * Detect repeated ref state declarations.
     */
    private function detectRefStatePatterns(string $template, string $script): ?string
    {
        $patterns = [
            'dialog open states' => '/const\s+\w*(Open|Visible|Shown)\s*=\s*ref\s*<?\s*boolean\s*>?\s*\(\s*false\s*\)/',
            'loading states' => '/const\s+\w*(Loading|Saving|Processing|Updating)\s*=\s*ref\s*<?\s*boolean\s*>?\s*\(\s*false\s*\)/',
            'selected item refs' => '/const\s+\w*(ToEdit|ToDelete|Selected|Current)\s*=\s*ref\s*<[^>]+\|\s*null\s*>\s*\(\s*null\s*\)/',
        ];

        foreach ($patterns as $name => $pattern) {
            $count = preg_match_all($pattern, $script);
            if ($count >= (int) $this->config('min_ref_states', 3)) {
                return "Found {$count} similar {$name} - consider a composable like useDialogState()";
            }
        }

        return null;
    }

    /**
     * Detect repeated handler function patterns.
     */
    private function detectHandlerPatterns(string $template, string $script): ?string
    {
        // Detect open* functions
        $openFunctions = preg_match_all('/function\s+open\w+\s*\(/', $script);
        if ($openFunctions >= (int) $this->config('min_open_functions', 4)) {
            return "Found {$openFunctions} open* functions - consider extracting dialog management into a composable";
        }

        // Detect on*Updated/on*Saved patterns
        $onHandlers = preg_match_all('/function\s+on\w+(Updated|Saved|Deleted|Created|Changed)\s*\(/', $script);
        if ($onHandlers >= (int) $this->config('min_on_handlers', 3)) {
            return "Found {$onHandlers} similar on* handlers - consider standardizing with a pattern";
        }

        return null;
    }

    /**
     * Detect repeated template section patterns.
     */
    private function detectSectionPatterns(string $template, string $script): ?string
    {
        // Detect repeated card patterns
        $cardCount = preg_match_all('/<Card[^>]*>.*?<CardHeader/s', $template);
        if ($cardCount >= (int) $this->config('min_card_patterns', 3)) {
            return "Found {$cardCount} similar Card patterns - consider a specialized card component";
        }

        return null;
    }

    /**
     * Detect similar v-model binding patterns.
     */
    private function detectSimilarVModelPatterns(string $template, string $script): ?string
    {
        preg_match_all('/v-model(?::\w+)?="([^"]+)"/', $template, $matches);

        if (!isset($matches[1]) || count($matches[1]) < 4) {
            return null;
        }

        // Look for patterns like form.fieldName
        $formBindings = array_filter($matches[1], fn ($m) => str_starts_with($m, 'form.'));
        if (count($formBindings) >= (int) $this->config('min_form_bindings', 6)) {
            return 'Found '.count($formBindings).' form field bindings - consider extracting form sections into components';
        }

        return null;
    }

    /**
     * Detect repeated CSS class patterns.
     */
    private function detectRepeatedClassPatterns(string $template, string $script): ?string
    {
        preg_match_all('/class="([^"]{30,})"/', $template, $matches);

        if (!isset($matches[1]) || count($matches[1]) < 3) {
            return null;
        }

        $classCounts = array_count_values($matches[1]);
        $duplicates = array_filter($classCounts, fn ($count) => $count >= (int) $this->config('min_class_duplicates', 3));

        if (!empty($duplicates)) {
            $count = array_sum($duplicates);
            $patterns = count($duplicates);

            return "Found {$patterns} repeated class patterns ({$count} occurrences) - consider extracting into components or CSS utilities";
        }

        return null;
    }

    /**
     * Detect repeated component opening tags with similar attributes.
     */
    private function detectRepeatedComponentPatterns(string $template, string $script): ?string
    {
        // Extract all component opening tags (PascalCase components)
        preg_match_all('/<([A-Z][a-zA-Z]+)(\s[^>]*)?>/s', $template, $matches, PREG_SET_ORDER);

        if (count($matches) < 3) {
            return null;
        }

        // Group by component name
        $componentUsages = [];
        foreach ($matches as $match) {
            $componentName = $match[1];
            $fullTag = $match[0];
            $normalized = preg_replace('/\s+/', ' ', trim($fullTag));

            if (!isset($componentUsages[$componentName])) {
                $componentUsages[$componentName] = [];
            }
            $componentUsages[$componentName][] = $normalized;
        }

        $warnings = [];
        foreach ($componentUsages as $componentName => $usages) {
            $minComponentDuplicates = (int) $this->config('min_component_duplicates', 3);
            if (count($usages) < $minComponentDuplicates) {
                continue;
            }

            $tagCounts = array_count_values($usages);
            $duplicateTags = array_filter($tagCounts, fn ($count) => $count >= $minComponentDuplicates);

            if (!empty($duplicateTags)) {
                $totalDupes = array_sum($duplicateTags);
                $warnings[] = "<{$componentName}> used {$totalDupes}x with identical attributes - consider wrapper component";
            }
        }

        return !empty($warnings) ? implode('; ', array_slice($warnings, 0, 2)) : null;
    }
}
