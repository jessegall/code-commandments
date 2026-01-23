<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Warning;
use JesseGall\CodeCommandments\Support\Pipes\MatchResult;
use JesseGall\CodeCommandments\Support\Pipes\Vue\VueContext;
use JesseGall\CodeCommandments\Support\Pipes\Vue\VuePipeline;

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

        return VuePipeline::make($filePath, $content)
            ->extractTemplate()
            ->returnRighteousIfNoTemplate()
            ->pipe(fn (VueContext $ctx) => $ctx->with(matches: $this->findTypeAssertions($ctx)))
            ->mapToWarnings(fn (VueContext $ctx) => array_map(
                fn (MatchResult $match) => Warning::at(
                    $match->line,
                    $match->groups['message'],
                    $match->groups['suggestion']
                ),
                $ctx->matches
            ))
            ->judge();
    }

    private function findTypeAssertions(VueContext $ctx): array
    {
        $templateContent = $ctx->getSectionContent();
        $matches = [];

        // Look for type assertions in bindings
        preg_match_all('/:[a-z-]+="[^"]*\s+as\s+[A-Za-z]+/', $templateContent, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($found as $match) {
            if (!str_contains($match[0][0], 'as const')) {
                $matches[] = new MatchResult(
                    name: 'type_assertion_binding',
                    pattern: '',
                    match: $match[0][0],
                    line: $ctx->getLineFromOffset($match[0][1]),
                    offset: $match[0][1],
                    content: null,
                    groups: [
                        'message' => 'TypeScript type assertion in template binding',
                        'suggestion' => 'Move type assertion to a computed property in script',
                    ],
                );
            }
        }

        // Look for type assertions in slot template bindings
        preg_match_all('/<template\s+#[a-z-]+="[^"]*\s+as\s+[A-Za-z]+/', $templateContent, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($found as $match) {
            $matchText = $match[0][0];
            if (!str_contains($matchText, 'as const') && !preg_match('/}:\s*\{/', $matchText)) {
                $matches[] = new MatchResult(
                    name: 'type_assertion_slot',
                    pattern: '',
                    match: $matchText,
                    line: $ctx->getLineFromOffset($match[0][1]),
                    offset: $match[0][1],
                    content: null,
                    groups: [
                        'message' => 'TypeScript type assertion in slot binding',
                        'suggestion' => 'Use type annotation instead: #slot="{ data }: { data: Type }"',
                    ],
                );
            }
        }

        return $matches;
    }
}
