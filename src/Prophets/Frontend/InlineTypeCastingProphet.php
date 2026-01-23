<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Results\Judgment;

/**
 * Avoid inline type casting in template bindings.
 *
 * Don't use TypeScript type assertions (as) in template bindings.
 * Instead, create a properly typed computed property or use type guards.
 */
class InlineTypeCastingProphet extends FrontendCommandment
{
    public function applicableExtensions(): array
    {
        return ['vue'];
    }

    public function description(): string
    {
        return 'Avoid inline type casting in template bindings';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Don't use TypeScript type assertions (as) in template bindings.
Instead, create a properly typed computed property or use type guards.

Bad:
    :items="(data as ItemData[])"
    :user="(currentUser as UserData)"

Good:
    // In script:
    const items = computed(() => data as ItemData[])
    const user = computed(() => currentUser as UserData)

    // In template:
    :items="items"
    :user="user"

Allowed:
    - "as const" is valid
    - Type annotations in slot props: <template #slot="{ data }: { data: Type }">
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        if ($this->shouldSkipExtension($filePath)) {
            return $this->righteous();
        }

        $template = $this->extractTemplate($content);

        if ($template === null) {
            return $this->skip('No template section found');
        }

        $templateContent = $template['content'];
        $templateStart = $template['start'];
        $sins = [];

        // Look for type assertions in bindings: :[prop]="... as SomeType"
        $pattern = '/:[a-z-]+="[^"]*\s+as\s+[A-Za-z]+/';

        preg_match_all($pattern, $templateContent, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($matches as $match) {
            // Skip "as const" which is valid
            if (str_contains($match[0][0], 'as const')) {
                continue;
            }

            $offset = $match[0][1];
            $line = $this->getLineFromOffset($content, $templateStart + $offset);

            $sins[] = $this->sinAt(
                $line,
                'TypeScript type assertion in template binding',
                $this->getSnippet($templateContent, $offset, 50),
                'Move type assertion to a computed property in script'
            );
        }

        // Look for type assertions in slot template bindings but NOT type annotations
        // Type annotations like #slot="{ data }: { data: Type }" are fine
        // Type assertions like #slot="data as Type" are not
        $slotPattern = '/<template\s+#[a-z-]+="[^"]*\s+as\s+[A-Za-z]+/';

        preg_match_all($slotPattern, $templateContent, $slotMatches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($slotMatches as $match) {
            $matchText = $match[0][0];

            // Skip "as const"
            if (str_contains($matchText, 'as const')) {
                continue;
            }

            // Skip type annotations (has }: { pattern before the type)
            // e.g., #slot="{ data }: { data: Type }" - this is NOT a cast
            if (preg_match('/}:\s*\{/', $matchText)) {
                continue;
            }

            $offset = $match[0][1];
            $line = $this->getLineFromOffset($content, $templateStart + $offset);

            $sins[] = $this->sinAt(
                $line,
                'TypeScript type assertion in slot binding',
                $this->getSnippet($templateContent, $offset, 50),
                'Use type annotation instead: #slot="{ data }: { data: Type }"'
            );
        }

        return empty($sins) ? $this->righteous() : Judgment::withWarnings($sins);
    }
}
