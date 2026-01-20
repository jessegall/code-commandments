<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Results\Judgment;

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

        $template = $this->extractTemplate($content);

        if ($template === null) {
            return $this->skip('No template section found');
        }

        $templateContent = $template['content'];
        $templateStart = $template['start'];
        $sins = [];

        // Check for v-model:checked on Switch or Checkbox
        $vModelCheckedPattern = '/<(Switch|Checkbox)[^>]*v-model:checked/';

        if (preg_match($vModelCheckedPattern, $templateContent, $match, PREG_OFFSET_CAPTURE)) {
            $offset = $match[0][1];
            $line = $this->getLineFromOffset($content, $templateStart + $offset);

            $sins[] = $this->sinAt(
                $line,
                'v-model:checked used on '.$match[1][0],
                $this->getSnippet($templateContent, $offset, 50),
                'Use v-model instead of v-model:checked'
            );
        }

        // Check for :checked on Switch or Checkbox (without v-model)
        $colonCheckedPattern = '/<(Switch|Checkbox)[^>]*:checked=/';

        if (preg_match($colonCheckedPattern, $templateContent, $match, PREG_OFFSET_CAPTURE)) {
            $offset = $match[0][1];
            $line = $this->getLineFromOffset($content, $templateStart + $offset);

            $sins[] = $this->sinAt(
                $line,
                ':checked used on '.$match[1][0],
                $this->getSnippet($templateContent, $offset, 50),
                'Use v-model instead of :checked with @update:checked'
            );
        }

        return empty($sins) ? $this->righteous() : $this->fallen($sins);
    }
}
