<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Results\Judgment;

/**
 * Never hardcode URLs in href attributes.
 *
 * Use wayfinder-generated route helpers for all navigation.
 * This provides type safety and ensures URLs stay in sync with backend routes.
 */
class WayfinderRoutesProphet extends FrontendCommandment
{
    public function applicableExtensions(): array
    {
        return ['vue'];
    }

    public function description(): string
    {
        return 'Never hardcode URLs in href attributes';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Never hardcode URLs in href attributes.

Use wayfinder-generated route helpers for all navigation. This provides
type safety and ensures URLs stay in sync with backend routes.

Bad:
    <Link href="/products">Products</Link>
    <a href="/orders/123">View Order</a>

Good:
    <Link :href="products.index.url()">Products</Link>
    <Link :href="orders.show.url(order.id)">View Order</Link>
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

        // Check for hardcoded URLs in href attributes
        // Matches: href="/..." or :href="'/..." or :href="`/..."
        $pattern = '/(href|:href)="[`\'"]\/[a-z]/i';

        preg_match_all($pattern, $templateContent, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($matches as $match) {
            $offset = $match[0][1];
            $line = $this->getLineFromOffset($content, $templateStart + $offset);

            $sins[] = $this->sinAt(
                $line,
                'Hardcoded URL in href attribute',
                $this->getSnippet($templateContent, $offset, 60),
                'Use :href="products.index.url()" with wayfinder helpers'
            );
        }

        return empty($sins) ? $this->righteous() : $this->fallen($sins);
    }
}
