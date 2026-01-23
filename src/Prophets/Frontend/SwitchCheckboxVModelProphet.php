<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\Pipes\MatchResult;
use JesseGall\CodeCommandments\Support\Pipes\Vue\VueContext;
use JesseGall\CodeCommandments\Support\Pipes\Vue\VuePipeline;

/**
 * Use v-model for Switch/Checkbox components, not v-model:checked or :checked.
 *
 * Always use v-model for Switch and Checkbox components.
 */
class SwitchCheckboxVModelProphet extends FrontendCommandment
{
    public function applicableExtensions(): array
    {
        return ['vue'];
    }

    public function description(): string
    {
        return 'Use v-model for Switch/Checkbox components, not v-model:checked or :checked';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Always use v-model for Switch and Checkbox components.

Never use v-model:checked or :checked with @update:checked - these patterns
don't work correctly with our Switch/Checkbox components.

Bad:
    <Switch v-model:checked="form.enabled" />
    <Checkbox v-model:checked="form.active" />
    <Switch :checked="value" @update:checked="value = $event" />

Good:
    <Switch v-model="form.enabled" />
    <Checkbox v-model="form.active" />
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
            ->pipe(fn (VueContext $ctx) => $ctx->with(matches: array_merge(
                $this->findPattern($ctx, '/<(Switch|Checkbox)[^>]*v-model:checked/', 'v-model:checked'),
                $this->findPattern($ctx, '/<(Switch|Checkbox)[^>]*:checked=/', ':checked')
            )))
            ->forEachMatch(function (MatchResult $match, VuePipeline $pipeline) {
                $type = $match->groups['type'];
                $component = $match->groups['component'];

                return $pipeline->sinAt(
                    $match->offset,
                    "{$type} used on {$component}",
                    $pipeline->getSnippet($match->offset, 50),
                    "Use v-model instead of {$type}"
                );
            })
            ->judge();
    }

    private function findPattern(VueContext $ctx, string $pattern, string $type): array
    {
        $matches = [];
        $content = $ctx->getSectionContent();

        if ($content === null) {
            return [];
        }

        if (preg_match_all($pattern, $content, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($found as $match) {
                $matches[] = new MatchResult(
                    name: 'switch_checkbox_vmodel',
                    pattern: $pattern,
                    match: $match[0][0],
                    line: $ctx->getLineFromOffset($match[0][1]),
                    offset: $match[0][1],
                    content: null,
                    groups: [
                        'type' => $type,
                        'component' => $match[1][0],
                    ],
                );
            }
        }

        return $matches;
    }
}
