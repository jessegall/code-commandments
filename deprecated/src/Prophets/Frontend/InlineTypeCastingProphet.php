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
            ->inTemplate()
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

        // Bindings: :prop="…" / v-bind:prop="…" / v-…="…". Inspect the value with
        // string literals stripped, so an `as` inside quoted content (e.g.
        // 'Copy outcome as JSON') is not mistaken for a type assertion (#20).
        preg_match_all('/(?::|v-)[A-Za-z0-9_:.-]+="([^"]*)"/', $templateContent, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($found as $match) {
            if ($this->hasTypeAssertion($match[1][0])) {
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

        // Slot template bindings: <template #slot="…">.
        preg_match_all('/<template\s+#[A-Za-z0-9_:.-]+="([^"]*)"/', $templateContent, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($found as $match) {
            $value = $match[1][0];

            // `#slot="{ data }: { data: Type }"` is a type annotation, not an assertion.
            if ($this->hasTypeAssertion($value) && !preg_match('/}\s*:\s*\{/', $value)) {
                $matches[] = new MatchResult(
                    name: 'type_assertion_slot',
                    pattern: '',
                    match: $match[0][0],
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

    /**
     * Whether a binding expression contains a TypeScript type assertion (`expr
     * as Type`), ignoring any `as` that appears inside a string literal and the
     * always-allowed `as const`.
     */
    private function hasTypeAssertion(string $expression): bool
    {
        // Remove single-quoted and template-literal string content (the binding
        // value is double-quoted, so its own strings use ' or `).
        $stripped = preg_replace(['/\'[^\']*\'/', '/`[^`]*`/'], ' ', $expression) ?? $expression;

        // An assertion is `<value> as <TypeStart>` — `as` preceded by a value
        // token and followed by an identifier/uppercase type. `as const` is fine.
        $withoutConst = preg_replace('/\bas\s+const\b/', ' ', $stripped) ?? $stripped;

        return (bool) preg_match('/[A-Za-z0-9_$)\]]\s+as\s+[A-Za-z_]/', $withoutConst);
    }
}
