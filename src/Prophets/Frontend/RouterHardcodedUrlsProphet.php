<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Sin;
use JesseGall\CodeCommandments\Support\PackageDetector;
use JesseGall\CodeCommandments\Support\Pipes\MatchResult;
use JesseGall\CodeCommandments\Support\Pipes\Vue\VueContext;
use JesseGall\CodeCommandments\Support\Pipes\Vue\VuePipeline;
use JesseGall\CodeCommandments\Support\RegexMatcher;
use JesseGall\CodeCommandments\Support\TextHelper;

/**
 * Never hardcode URLs in router.visit/push/replace calls.
 *
 * Use wayfinder-generated route helpers for programmatic navigation.
 */
class RouterHardcodedUrlsProphet extends FrontendCommandment
{
    public function supported(): bool
    {
        return PackageDetector::hasWayfinder();
    }

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

        return VuePipeline::make($filePath, $content)
            ->returnRighteousWhen(fn (VueContext $ctx) => $this->shouldSkip($ctx))
            ->pipe(fn (VueContext $ctx) => $this->findHardcodedUrls($ctx))
            ->sinsFromMatches(
                'Hardcoded URL in router call',
                'Use wayfinder-generated route helper: products.index.url()'
            )
            ->judge();
    }

    private function findHardcodedUrls(VueContext $ctx): VueContext
    {
        $pattern = '/router\.(visit|push|replace)\([\'"`]\//';
        $rawMatches = RegexMatcher::for($ctx->content)->matchAll($pattern);

        $matches = array_map(fn ($match) => new MatchResult(
            name: 'hardcoded_url',
            pattern: $pattern,
            match: $match['match'],
            line: TextHelper::getLineNumber($ctx->content, $match['offset']),
            offset: $match['offset'],
            content: TextHelper::getSnippet($ctx->content, $match['offset']),
            groups: $match['groups'],
        ), $rawMatches);

        return $ctx->with(matches: $matches);
    }

    private function shouldSkip(VueContext $ctx): bool
    {
        return $ctx->filePathContains('/routes/')
            || $ctx->filePathContains('/actions/');
    }
}
