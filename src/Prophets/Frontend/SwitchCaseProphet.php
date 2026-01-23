<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\Pipes\MatchResult;
use JesseGall\CodeCommandments\Support\Pipes\Vue\VueContext;
use JesseGall\CodeCommandments\Support\Pipes\Vue\VuePipeline;

/**
 * Use SwitchCase component instead of v-if chains comparing the same variable.
 *
 * When conditionally rendering based on a string value with multiple cases,
 * use the SwitchCase component instead of v-if/v-else-if chains.
 */
class SwitchCaseProphet extends FrontendCommandment
{
    private const MIN_CASES_THRESHOLD = 3;

    public function applicableExtensions(): array
    {
        return ['vue'];
    }

    public function description(): string
    {
        return 'Use SwitchCase component instead of v-if chains comparing the same variable';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
When conditionally rendering based on a string value with multiple cases,
use the SwitchCase component instead of v-if/v-else-if chains.

Bad:
    <div v-if="status === 'pending'">Pending...</div>
    <div v-else-if="status === 'success'">Success!</div>
    <div v-else-if="status === 'error'">Error</div>

Good:
    <SwitchCase :value="status">
        <template #pending>Pending...</template>
        <template #success>Success!</template>
        <template #error>Error</template>
    </SwitchCase>

Also, when you have complex conditions that are mutually exclusive,
extract them to a computed property that returns a state string:

Bad:
    <div v-if="a === 'x'">...</div>
    <div v-if="a === 'c' && b === 'c'">...</div>
    <div v-if="isLoading || hasError">...</div>

Good:
    const viewState = computed(() => {
        if (a === 'x') return 'x';
        if (a === 'c' && b === 'c') return 'cc';
        if (isLoading || hasError) return 'blocked';
        return 'default';
    });

    <SwitchCase :value="viewState">
        <template #x>...</template>
        <template #cc>...</template>
        <template #blocked>...</template>
        <template #default>...</template>
    </SwitchCase>

Benefits:
- Cleaner, more declarative syntax
- Easier to add/remove cases
- Complex logic is in JavaScript, not templates
- Named slots make the intent clear
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
            ->pipe(fn (VueContext $ctx) => $ctx->with(matches: $this->findViolations($ctx)))
            ->forEachMatch(function (MatchResult $match, VuePipeline $pipeline) {
                return $pipeline->sinAt(
                    $match->offset,
                    $match->groups['message'],
                    null,
                    $match->groups['suggestion']
                );
            })
            ->judge();
    }

    private function findViolations(VueContext $ctx): array
    {
        $templateContent = $ctx->getSectionContent();
        $matches = [];

        foreach ($this->findVIfChains($templateContent) as $chain) {
            if ($chain['count'] >= self::MIN_CASES_THRESHOLD) {
                $matches[] = new MatchResult(
                    name: 'v_if_chain',
                    pattern: '',
                    match: '',
                    line: $ctx->getLineFromOffset($chain['offset']),
                    offset: $chain['offset'],
                    content: null,
                    groups: [
                        'message' => "Found {$chain['count']} v-if/v-else-if cases comparing '{$chain['variable']}' - consider using SwitchCase component",
                        'suggestion' => 'Use <SwitchCase :value="'.$chain['variable'].'"> with named slots',
                    ],
                );
            }
        }

        foreach ($this->findComplexConditionChains($templateContent) as $chain) {
            if ($chain['count'] >= self::MIN_CASES_THRESHOLD) {
                $matches[] = new MatchResult(
                    name: 'complex_condition_chain',
                    pattern: '',
                    match: '',
                    line: $ctx->getLineFromOffset($chain['offset']),
                    offset: $chain['offset'],
                    content: null,
                    groups: [
                        'message' => "Found {$chain['count']} complex v-if conditions - consider extracting to a computed state property with SwitchCase",
                        'suggestion' => 'Extract conditions to computed property returning state string, then use SwitchCase',
                    ],
                );
            }
        }

        return $matches;
    }

    /**
     * Find v-if chains that compare the same variable to string literals.
     *
     * @return array<array{variable: string, count: int, offset: int}>
     */
    private function findVIfChains(string $content): array
    {
        $chains = [];
        $lines = explode("\n", $content);
        $currentChain = null;
        $offset = 0;

        foreach ($lines as $line) {
            // Check for v-if="variable === 'value'"
            if (preg_match('/v-if="(\w+)\s*===\s*[\'"]([^\'"]+)[\'"]"/', $line, $matches)) {
                $variable = $matches[1];
                $currentChain = [
                    'variable' => $variable,
                    'count' => 1,
                    'offset' => $offset,
                ];
            } elseif ($currentChain && preg_match('/v-else-if="'.preg_quote($currentChain['variable'], '/').'\s*===\s*[\'"][^\'"]+[\'"]"/', $line)) {
                // Continue chain
                $currentChain['count']++;
            } elseif ($currentChain && preg_match('/v-else[^-]/', $line)) {
                // End of chain
                if ($currentChain['count'] >= self::MIN_CASES_THRESHOLD) {
                    $chains[] = $currentChain;
                }
                $currentChain = null;
            } elseif ($currentChain && preg_match('/v-if="/', $line)) {
                // New v-if breaks the chain
                if ($currentChain['count'] >= self::MIN_CASES_THRESHOLD) {
                    $chains[] = $currentChain;
                }
                $currentChain = null;
            }

            $offset += strlen($line) + 1;
        }

        // Save last chain if valid
        if ($currentChain && $currentChain['count'] >= self::MIN_CASES_THRESHOLD) {
            $chains[] = $currentChain;
        }

        return $chains;
    }

    /**
     * Find v-if chains with complex conditions (using && or ||).
     *
     * @return array<array{count: int, offset: int}>
     */
    private function findComplexConditionChains(string $content): array
    {
        $chains = [];
        $lines = explode("\n", $content);
        $currentChain = null;
        $offset = 0;

        foreach ($lines as $line) {
            // Check for v-if with complex condition (contains && or ||)
            if (preg_match('/v-if="[^"]*(\&\&|\|\|)[^"]*"/', $line)) {
                if ($currentChain === null) {
                    $currentChain = [
                        'count' => 1,
                        'offset' => $offset,
                    ];
                }
            } elseif ($currentChain && preg_match('/v-else-if="[^"]*(\&\&|\|\|)[^"]*"/', $line)) {
                // Complex v-else-if continues the chain
                $currentChain['count']++;
            } elseif ($currentChain && preg_match('/v-else-if="[^"]*"/', $line)) {
                // Simple v-else-if still part of chain
                $currentChain['count']++;
            } elseif ($currentChain && preg_match('/v-else[^-]/', $line)) {
                // v-else ends the chain
                if ($currentChain['count'] >= self::MIN_CASES_THRESHOLD) {
                    $chains[] = $currentChain;
                }
                $currentChain = null;
            } elseif (preg_match('/v-if="/', $line)) {
                // New v-if - save current and start new if complex
                if ($currentChain && $currentChain['count'] >= self::MIN_CASES_THRESHOLD) {
                    $chains[] = $currentChain;
                }
                $currentChain = null;
            }

            $offset += strlen($line) + 1;
        }

        // Save last chain
        if ($currentChain && $currentChain['count'] >= self::MIN_CASES_THRESHOLD) {
            $chains[] = $currentChain;
        }

        return $chains;
    }
}
