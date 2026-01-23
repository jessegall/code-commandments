<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\Pipes\MatchResult;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpContext;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpPipeline;

/**
 * Route URIs must use kebab-case.
 */
class KebabCaseRoutesProphet extends PhpCommandment
{
    public function description(): string
    {
        return 'Route URIs must use kebab-case';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Route URIs must use kebab-case for consistency and web standards.

Bad:
    Route::get('/userProfile', ...);
    Route::get('/user_profile', ...);
    Route::get('/UserProfile', ...);

Good:
    Route::get('/user-profile', ...);
    Route::get('/orders/{orderId}', ...);
    Route::get('/feeds/{feed}/reviews.xml', ...);

Exceptions (not checked):
- Route parameters: {orderId}, {slug}
- File extensions: .xml, .json, .apk, etc.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        return PhpPipeline::make($filePath, $content)
            ->returnRighteousWhen(fn (PhpContext $ctx) => ! $this->isRouteFile($ctx->filePath))
            ->pipe(fn (PhpContext $ctx) => $this->findNonKebabRoutes($ctx))
            ->sinsFromMatches(
                fn (MatchResult $m) => sprintf('Route URI is not kebab-case: "%s" (found: %s)', $m->groups['uri'], implode(', ', $m->groups['badSegments'])),
                fn (MatchResult $m) => sprintf('Use kebab-case: "%s"', $this->toKebabCase($m->groups['uri']))
            )
            ->judge();
    }

    private function isRouteFile(string $filePath): bool
    {
        return str_contains($filePath, '/routes/') || str_contains($filePath, 'routes.php');
    }

    private function findNonKebabRoutes(PhpContext $ctx): PhpContext
    {
        $matches = [];
        $lines = explode("\n", $ctx->content);

        foreach ($lines as $lineNum => $line) {
            if (preg_match_all('/(?:Route::|->)(?:get|post|put|patch|delete|any|match|prefix|resource|apiResource)\s*\(\s*[\'"]([^\'"]+)[\'"]/', $line, $routeMatches, PREG_SET_ORDER)) {
                foreach ($routeMatches as $match) {
                    $uri = $match[1];
                    $badSegments = $this->findNonKebabSegments($uri);

                    if (! empty($badSegments)) {
                        $matches[] = new MatchResult(
                            name: 'non_kebab_route',
                            pattern: '',
                            match: $uri,
                            line: $lineNum + 1,
                            offset: null,
                            content: trim($line),
                            groups: [
                                'uri' => $uri,
                                'badSegments' => $badSegments,
                            ],
                        );
                    }
                }
            }
        }

        return $ctx->with(matches: $matches);
    }

    /**
     * @return array<string>
     */
    private function findNonKebabSegments(string $uri): array
    {
        $segments = explode('/', $uri);
        $bad = [];

        foreach ($segments as $segment) {
            if (empty($segment) || str_starts_with($segment, '{')) {
                continue;
            }

            // Strip file extension before checking (e.g., reviews.xml -> reviews)
            $segmentToCheck = $this->stripFileExtension($segment);

            // Valid kebab-case: lowercase letters, numbers, and hyphens only
            if (! preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $segmentToCheck)) {
                $bad[] = $segment;
            }
        }

        return $bad;
    }

    /**
     * Strip file extension from a segment.
     */
    private function stripFileExtension(string $segment): string
    {
        // Match any file extension at the end of the segment (e.g., .xml, .apk, .json)
        return preg_replace('/\.[a-z0-9]+$/i', '', $segment);
    }

    private function toKebabCase(string $uri): string
    {
        $segments = explode('/', $uri);

        $converted = array_map(function ($segment) {
            if (empty($segment) || str_starts_with($segment, '{')) {
                return $segment;
            }

            // Preserve file extension
            $extension = '';
            if (preg_match('/(\.[a-z0-9]+)$/i', $segment, $extMatch)) {
                $extension = strtolower($extMatch[1]);
                $segment = substr($segment, 0, -strlen($extension));
            }

            // Convert camelCase/PascalCase to kebab-case, then replace underscores
            $kebab = preg_replace('/([a-z])([A-Z])/', '$1-$2', $segment);
            $kebab = str_replace('_', '-', $kebab);

            return strtolower($kebab) . $extension;
        }, $segments);

        return implode('/', $converted);
    }
}
