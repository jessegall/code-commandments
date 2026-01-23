<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpContext;
use JesseGall\CodeCommandments\Support\Pipes\PipelineBuilder;

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

Route parameters like {orderId} are fine - only the URI segments are checked.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        return PipelineBuilder::make(PhpContext::from($filePath, $content))
            ->returnRighteousWhen(fn (PhpContext $ctx) => ! $this->isRouteFile($ctx->filePath))
            ->pipe(fn (PhpContext $ctx) => $this->findNonKebabRoutes($ctx))
            ->sinsFromMatches(
                fn ($m) => sprintf('Route URI is not kebab-case: "%s" (found: %s)', $m['uri'], implode(', ', $m['badSegments'])),
                fn ($m) => sprintf('Use kebab-case: "%s"', $this->toKebabCase($m['uri']))
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
                        $matches[] = [
                            'line' => $lineNum + 1,
                            'uri' => $uri,
                            'badSegments' => $badSegments,
                            'content' => trim($line),
                        ];
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

            // Valid kebab-case: lowercase letters, numbers, and hyphens only
            if (! preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $segment)) {
                $bad[] = $segment;
            }
        }

        return $bad;
    }

    private function toKebabCase(string $uri): string
    {
        $segments = explode('/', $uri);

        $converted = array_map(function ($segment) {
            if (empty($segment) || str_starts_with($segment, '{')) {
                return $segment;
            }

            // Convert camelCase/PascalCase to kebab-case, then replace underscores
            $kebab = preg_replace('/([a-z])([A-Z])/', '$1-$2', $segment);
            $kebab = str_replace('_', '-', $kebab);

            return strtolower($kebab);
        }, $segments);

        return implode('/', $converted);
    }
}
