<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\PackageDetector;
use JesseGall\CodeCommandments\Support\Pipes\Vue\VuePipeline;

/**
 * Never hardcode URLs in href attributes.
 *
 * Use wayfinder-generated route helpers for all navigation.
 * This provides type safety and ensures URLs stay in sync with backend routes.
 */
class WayfinderRoutesProphet extends FrontendCommandment
{
    public function supported(): bool
    {
        return PackageDetector::hasWayfinder();
    }

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

        return VuePipeline::make($filePath, $content)
            ->inTemplate()
            ->matchAll('/(href|:href)="[`\'"]\/[a-z]/i')
            ->sinsFromMatches(
                'Hardcoded URL in href attribute',
                'Use :href="products.index.url()" with wayfinder helpers'
            )
            ->judge();
    }
}
