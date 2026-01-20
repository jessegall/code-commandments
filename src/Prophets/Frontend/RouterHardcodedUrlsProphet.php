<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Results\Judgment;

/**
 * Never hardcode URLs in router.visit/push/replace calls.
 *
 * Use wayfinder-generated route helpers for programmatic navigation.
 */
class RouterHardcodedUrlsProphet extends FrontendCommandment
{
    public function applicableExtensions(): array
    {
        return ['vue', 'ts'];
    }

    public function description(): string
    {
        return 'Never hardcode URLs in router calls';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Never hardcode URLs in router.visit/push/replace calls.

Use wayfinder-generated route helpers for programmatic navigation.

Bad:
    router.visit('/products');
    router.push('/orders/' + orderId);

Good:
    router.visit(products.index.url());
    router.push(orders.show.url(orderId));
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        if ($this->shouldSkipExtension($filePath)) {
            return $this->righteous();
        }

        // Skip files in routes/ or actions/ directories
        if (str_contains($filePath, '/routes/') || str_contains($filePath, '/actions/')) {
            return $this->righteous();
        }

        $sins = [];

        // Check for router calls with hardcoded URLs starting with /
        $pattern = '/router\.(visit|push|replace)\([\'"`]\//';

        foreach ($this->findMatches($pattern, $content) as $match) {
            $line = $this->getLineNumber($content, $match[1]);
            $sins[] = $this->sinAt(
                $line,
                'Hardcoded URL in router call',
                $this->getSnippet($content, $match[1]),
                'Use wayfinder-generated route helper: products.index.url()'
            );
        }

        return empty($sins) ? $this->righteous() : $this->fallen($sins);
    }
}
