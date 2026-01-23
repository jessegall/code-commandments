<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\Pipes\Vue\VueContext;
use JesseGall\CodeCommandments\Support\Pipes\Vue\VuePipeline;

/**
 * Consider using slots instead of content-like props.
 *
 * Props that pass content (title, description, message, etc.) might be
 * better expressed as slots for more flexibility.
 */
class ContentLikePropsProphet extends FrontendCommandment
{
    protected array $contentProps = [
        'title', 'description', 'message', 'content', 'body',
        'text', 'label', 'subtitle', 'header', 'footer',
    ];

    public function applicableExtensions(): array
    {
        return ['vue'];
    }

    public function requiresConfession(): bool
    {
        return true;
    }

    public function description(): string
    {
        return 'Consider using slots instead of content-like props';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Props that pass content (title, description, message, etc.) might be
better expressed as slots for more flexibility.

Consider slots when:
- The content might need HTML/components
- The content varies significantly between uses
- The content might need styling or formatting

Bad:
    <Card
        title="My Title"
        description="Some long description text here"
    />

Good:
    <Card>
        <template #title>My Title</template>
        <template #description>
            Some <strong>formatted</strong> description
        </template>
    </Card>

Note: Simple labels and short text are fine as props.
This is a guideline for review, not a strict rule.
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
            ->mapToWarnings(function (VueContext $ctx) {
                $templateContent = $ctx->getSectionContent();
                $warnings = [];

                foreach ($this->contentProps as $prop) {
                    $pattern = '/' . preg_quote($prop, '/') . '="[^"]{50,}/';
                    if (preg_match($pattern, $templateContent)) {
                        $warnings[] = $this->warningAt(
                            1,
                            "Long content in '{$prop}' prop - consider using a slot instead",
                            'Slots provide more flexibility for HTML/formatting'
                        );
                    }
                }

                return $warnings;
            })
            ->judge();
    }
}
