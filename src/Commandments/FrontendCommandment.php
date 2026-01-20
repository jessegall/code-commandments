<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Commandments;

/**
 * Base class for frontend file commandments (Vue, TypeScript, JavaScript).
 * Uses regex-based analysis for most cases, with Node.js AST scripts for complex fixes.
 */
abstract class FrontendCommandment extends BaseCommandment
{
    public function applicableExtensions(): array
    {
        return ['vue', 'ts', 'js', 'tsx', 'jsx'];
    }

    /**
     * Extract the script section from a Vue SFC.
     *
     * @return array{content: string, start: int, end: int, lang: string|null}|null
     */
    protected function extractScript(string $content): ?array
    {
        // Match <script> or <script setup> or <script lang="ts"> etc.
        if (preg_match('/<script(\s+[^>]*)?>(.+?)<\/script>/s', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $attributes = $matches[1][0] ?? '';
            $scriptContent = $matches[2][0];
            $start = $matches[2][1];

            // Detect language
            $lang = null;
            if (preg_match('/lang=["\'](\w+)["\']/', $attributes, $langMatch)) {
                $lang = $langMatch[1];
            }

            return [
                'content' => $scriptContent,
                'start' => $start,
                'end' => $start + strlen($scriptContent),
                'lang' => $lang,
                'setup' => str_contains($attributes, 'setup'),
            ];
        }

        return null;
    }

    /**
     * Extract the template section from a Vue SFC.
     *
     * Handles nested <template> elements (like <template v-if>) correctly
     * by counting nesting depth to find the matching closing tag.
     *
     * @return array{content: string, start: int, end: int}|null
     */
    protected function extractTemplate(string $content): ?array
    {
        // Find the opening <template> tag (Vue SFC root template)
        if (!preg_match('/<template(\s+[^>]*)?>/', $content, $openMatch, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $openTagStart = $openMatch[0][1];
        $openTagEnd = $openTagStart + strlen($openMatch[0][0]);
        $contentStart = $openTagEnd;

        // Find matching closing </template> by counting nesting depth
        $depth = 1;
        $pos = $openTagEnd;
        $length = strlen($content);

        while ($depth > 0 && $pos < $length) {
            // Find next <template or </template
            $nextOpen = preg_match('/<template(\s+[^>]*)?>/', $content, $m, PREG_OFFSET_CAPTURE, $pos) ? $m[0][1] : PHP_INT_MAX;
            $nextClose = preg_match('/<\/template\s*>/', $content, $m, PREG_OFFSET_CAPTURE, $pos) ? $m[0][1] : PHP_INT_MAX;

            if ($nextClose === PHP_INT_MAX) {
                // No closing tag found
                return null;
            }

            if ($nextOpen < $nextClose) {
                // Found another opening tag first - increase depth
                $depth++;
                preg_match('/<template(\s+[^>]*)?>/', $content, $m, PREG_OFFSET_CAPTURE, $pos);
                $pos = $m[0][1] + strlen($m[0][0]);
            } else {
                // Found closing tag first - decrease depth
                $depth--;
                preg_match('/<\/template\s*>/', $content, $m, PREG_OFFSET_CAPTURE, $pos);
                if ($depth === 0) {
                    // This is our matching closing tag
                    $contentEnd = $m[0][1];
                    return [
                        'content' => substr($content, $contentStart, $contentEnd - $contentStart),
                        'start' => $contentStart,
                        'end' => $contentEnd,
                    ];
                }
                $pos = $m[0][1] + strlen($m[0][0]);
            }
        }

        return null;
    }

    /**
     * Extract the style section from a Vue SFC.
     *
     * @return array{content: string, start: int, end: int, scoped: bool, lang: string|null}|null
     */
    protected function extractStyle(string $content): ?array
    {
        if (preg_match('/<style(\s+[^>]*)?>(.+?)<\/style>/s', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $attributes = $matches[1][0] ?? '';

            $lang = null;
            if (preg_match('/lang=["\'](\w+)["\']/', $attributes, $langMatch)) {
                $lang = $langMatch[1];
            }

            return [
                'content' => $matches[2][0],
                'start' => $matches[2][1],
                'end' => $matches[2][1] + strlen($matches[2][0]),
                'scoped' => str_contains($attributes, 'scoped'),
                'lang' => $lang,
            ];
        }

        return null;
    }

    /**
     * Check if this is a Vue SFC file.
     */
    protected function isVueSfc(string $filePath, string $content): bool
    {
        return pathinfo($filePath, PATHINFO_EXTENSION) === 'vue'
            || (str_contains($content, '<template') && str_contains($content, '<script'));
    }

    /**
     * Check if this is a TypeScript file.
     */
    protected function isTypeScript(string $filePath, string $content): bool
    {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);

        if (in_array($extension, ['ts', 'tsx'], true)) {
            return true;
        }

        // Check for Vue SFC with TypeScript
        if ($extension === 'vue') {
            $script = $this->extractScript($content);

            return $script !== null && $script['lang'] === 'ts';
        }

        return false;
    }

    /**
     * Check if the script uses Composition API.
     */
    protected function usesCompositionApi(string $scriptContent): bool
    {
        // Check for <script setup>
        if (preg_match('/<script[^>]+setup/', $scriptContent)) {
            return true;
        }

        // Check for setup() function
        if (preg_match('/setup\s*\([^)]*\)\s*{/', $scriptContent)) {
            return true;
        }

        // Check for Composition API imports
        $compositionImports = ['ref', 'reactive', 'computed', 'watch', 'watchEffect', 'onMounted', 'defineComponent'];
        foreach ($compositionImports as $import) {
            if (preg_match('/import\s*{[^}]*\b' . $import . '\b[^}]*}\s*from\s*[\'"]vue[\'"]/', $scriptContent)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the script uses Options API.
     */
    protected function usesOptionsApi(string $scriptContent): bool
    {
        // Check for Options API patterns
        $optionsPatterns = [
            '/export\s+default\s*{/',
            '/\bdata\s*\(\s*\)\s*{/',
            '/\bmethods\s*:\s*{/',
            '/\bcomputed\s*:\s*{/',
            '/\bwatch\s*:\s*{/',
            '/\bcomponents\s*:\s*{/',
            '/\bprops\s*:\s*{/',
            '/\bmounted\s*\(\s*\)/',
            '/\bcreated\s*\(\s*\)/',
        ];

        foreach ($optionsPatterns as $pattern) {
            if (preg_match($pattern, $scriptContent)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Count lines in content.
     */
    protected function countLines(string $content): int
    {
        return substr_count($content, "\n") + 1;
    }

    /**
     * Get line number from character offset.
     */
    protected function getLineFromOffset(string $content, int $offset): int
    {
        return substr_count(substr($content, 0, $offset), "\n") + 1;
    }
}
