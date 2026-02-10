<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Contracts\SinRepenter;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\RepentanceResult;
use JesseGall\CodeCommandments\Support\Pipes\MatchResult;
use JesseGall\CodeCommandments\Support\Pipes\Vue\VuePipeline;
use JesseGall\CodeCommandments\Support\RegexMatcher;
use JesseGall\CodeCommandments\Support\Str;

/**
 * Props should be bound using kebab-case in templates.
 *
 * Vue convention recommends kebab-case for prop bindings in templates
 * to maintain consistency with HTML attribute naming conventions.
 */
class KebabCasePropsProphet extends FrontendCommandment implements SinRepenter
{
    private const CAMEL_CASE_BINDING_PATTERN = '/(?::|v-bind:)([a-z]+[A-Z][a-zA-Z]*)\s*=/';

    public function applicableExtensions(): array
    {
        return ['vue'];
    }

    public function description(): string
    {
        return 'Props should be bound using kebab-case in templates';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Props should be bound using kebab-case in Vue templates.

This follows Vue's recommended convention and maintains consistency
with HTML attribute naming conventions.

Bad:
    <MyComponent :someValue="data" />
    <MyComponent :userName="name" />

Good:
    <MyComponent :some-value="data" />
    <MyComponent :user-name="name" />

Note: This only applies to bound props (with :), not regular attributes.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        if ($this->shouldSkipExtension($filePath)) {
            return $this->righteous();
        }

        return VuePipeline::make($filePath, $content)
            ->inTemplate()
            ->matchAll(self::CAMEL_CASE_BINDING_PATTERN)
            ->forEachMatch(function (MatchResult $match, VuePipeline $pipeline) {
                $propName = $match->groups[1];
                $kebabCase = Str::toKebabCase($propName);

                return $pipeline->sinAt(
                    $match->offset,
                    'Prop binding uses camelCase instead of kebab-case',
                    trim($match->match),
                    "Use :{$kebabCase}= instead of :{$propName}="
                );
            })
            ->judge();
    }

    public function canRepent(string $filePath): bool
    {
        return pathinfo($filePath, PATHINFO_EXTENSION) === 'vue';
    }

    public function repent(string $filePath, string $content): RepentanceResult
    {
        if (! $this->canRepent($filePath)) {
            return RepentanceResult::unchanged();
        }

        $pipeline = VuePipeline::make($filePath, $content)->extractTemplate();

        if ($pipeline->shouldSkip() || $pipeline->getSectionContent() === null) {
            return RepentanceResult::unchanged();
        }

        $penance = [];
        $pattern = '/(:|v-bind:)([a-z]+[A-Z][a-zA-Z]*)(\s*=)/';

        $newTemplateContent = RegexMatcher::for($pipeline->getSectionContent())
            ->replaceWith($pattern, function ($matches) use (&$penance) {
                $prefix = $matches[1];
                $propName = $matches[2];
                $suffix = $matches[3];
                $kebabCase = Str::toKebabCase($propName);

                $penance[] = "Converted :{$propName} to :{$kebabCase}";

                return $prefix.$kebabCase.$suffix;
            });

        if ($newTemplateContent === $pipeline->getSectionContent() || empty($penance)) {
            return RepentanceResult::unchanged();
        }

        $newContent = substr($content, 0, $pipeline->getSectionStart())
            .$newTemplateContent
            .substr($content, $pipeline->getSectionStart() + strlen($pipeline->getSectionContent()));

        return RepentanceResult::absolved($newContent, $penance);
    }
}
