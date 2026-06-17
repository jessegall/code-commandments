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
 * Use whenever() instead of watch() with if condition.
 *
 * When you have a watch that only executes when a condition is truthy,
 * use the `whenever` composable from VueUse instead.
 */
class WatchIfPatternProphet extends FrontendCommandment
{
    public function applicableExtensions(): array
    {
        return ['vue'];
    }

    public function description(): string
    {
        return 'Use whenever() instead of watch() with if condition';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
When a watch's callback runs ONLY when the WATCHED VALUE is truthy, use the
`whenever` composable from VueUse instead — it is cleaner and declarative.

The convertible shape is narrow: the callback's body is gated on the watched
value itself, either as `if (value) { … }` wrapping the whole body, or as an
`if (!value) return` early guard. The condition must be the callback's first
parameter — not unrelated state.

Bad:
    watch(isReady, (value) => {
        if (value) {
            doSomething()
        }
    })

Good:
    whenever(isReady, () => {
        doSomething()
    })

DOES NOT fire — a guard on unrelated state (`if (current === null) return`),
a check on a property/comparison (`if (value.length)`, `if (a !== b)`), an
`if` acting when the value is FALSY, or any `if` whose condition is not the
watched parameter. Converting those to `whenever` would change behaviour.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        if ($this->shouldSkipExtension($filePath)) {
            return $this->righteous();
        }

        return VuePipeline::make($filePath, $content)
            ->inScript()
            ->pipe(fn (VueContext $ctx) => $ctx->with(matches: $this->findWatchGuards($ctx)))
            ->mapToWarnings(fn (VueContext $ctx) => array_map(
                fn (MatchResult $match) => Warning::at(
                    $match->line,
                    'watch() whose callback only runs when the watched value is truthy — use whenever() from VueUse instead.',
                    'Replace `watch(source, v => { if (v) { … } })` with `whenever(source, () => { … })`.'
                ),
                $ctx->matches
            ))
            ->judge();
    }

    /**
     * @return list<MatchResult>
     */
    private function findWatchGuards(VueContext $ctx): array
    {
        $script = $ctx->getSectionContent();
        $matches = [];

        // watch( <source> , (param[, ...]) => {   — capture the first callback
        // parameter and the position just after the opening brace.
        $pattern = '/\bwatch\s*\(\s*[\s\S]*?,\s*(?:async\s*)?\(\s*([A-Za-z_$][\w$]*)[^)]*\)\s*=>\s*\{/';

        if (! preg_match_all($pattern, $script, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return $matches;
        }

        foreach ($found as $match) {
            $param = $match[1][0];
            $bodyStart = $match[0][1] + strlen($match[0][0]);
            $body = substr($script, $bodyStart);

            if ($this->bodyGuardsOnParam($body, $param)) {
                $matches[] = new MatchResult(
                    name: 'watch_if_on_value',
                    pattern: '',
                    match: $match[0][0],
                    line: $ctx->getLineFromOffset($match[0][1]),
                    offset: $match[0][1],
                    content: null,
                    groups: [],
                );
            }
        }

        return $matches;
    }

    /**
     * The callback body is gated on the watched value itself — `if (param) {`
     * wrapping the body, or an `if (!param) return` early guard. The condition
     * must be EXACTLY the parameter, so guards on unrelated state don't match.
     */
    private function bodyGuardsOnParam(string $body, string $param): bool
    {
        $p = preg_quote($param, '/');

        // `if (param) {` as the first statement (optionally after comments).
        if (preg_match('/^\s*(?:\/\/[^\n]*\n\s*|\/\*[\s\S]*?\*\/\s*)*if\s*\(\s*' . $p . '\s*\)\s*\{/', $body)) {
            return true;
        }

        // `if (!param) return` early guard (with or without a block).
        return (bool) preg_match('/^\s*(?:\/\/[^\n]*\n\s*|\/\*[\s\S]*?\*\/\s*)*if\s*\(\s*!\s*' . $p . '\s*\)\s*(?:return\b|\{\s*return\b)/', $body);
    }
}
