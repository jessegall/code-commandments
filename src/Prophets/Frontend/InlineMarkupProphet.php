<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\Pipes\Vue\VueContext;
use JesseGall\CodeCommandments\Support\Pipes\Vue\VuePipeline;

/**
 * Avoid excessive native HTML markup - extract components instead.
 *
 * Templates cluttered with raw HTML elements indicate implementation details
 * that should be extracted into dedicated, named components.
 */
class InlineMarkupProphet extends FrontendCommandment
{
    public function requiresConfession(): bool
    {
        return true;
    }

    public function applicableExtensions(): array
    {
        return ['vue'];
    }

    public function description(): string
    {
        return 'Avoid excessive native HTML markup - extract components instead';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Templates cluttered with native HTML elements (<div>, <span>, <h3>, <button>, etc.)
indicate implementation details that should be extracted into dedicated, named components.
Clean templates read like high-level compositions of components.

Bad:
<template>
    <div class="product-page">
        <div class="header">
            <h1>{{ product.name }}</h1>
            <span class="badge">{{ product.status }}</span>
        </div>
        <div class="details">
            <p>{{ product.description }}</p>
            <div class="pricing">
                <span class="price">{{ product.price }}</span>
                <button @click="addToCart">Add to cart</button>
            </div>
        </div>
    </div>
</template>

Good:
<template>
    <ProductPage>
        <ProductHeader :product="product" />
        <ProductDetails :product="product" @add-to-cart="addToCart" />
    </ProductPage>
</template>

Solutions:
- Extract groups of HTML elements into named components
- Use composition to break up complex templates
- Think of each component as a single responsibility
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        if ($this->shouldSkipExtension($filePath)) {
            return $this->righteous();
        }

        return VuePipeline::make($filePath, $content)
            ->inTemplate()
            ->mapToWarnings(function (VueContext $ctx) {
                $templateContent = $ctx->getSectionContent();

                $maxHtmlTags = (int) $this->config('max_html_tags', 15);

                $count = preg_match_all(
                    '/<(?!template\b|slot\b|!--)([a-z][a-z0-9]*)\b/',
                    $templateContent
                );

                if ($count > $maxHtmlTags) {
                    return $this->warningAt(
                        1,
                        "{$count} native HTML tags found - consider extracting components",
                        'Extract groups of HTML elements into named components'
                    );
                }

                return null;
            })
            ->judge();
    }
}
