<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpContext;
use JesseGall\CodeCommandments\Support\Pipes\PipelineBuilder;

/**
 * Commandment: #[Hidden] on public properties not returned to frontend.
 *
 * Properties with #[FromContainer], #[FromSession], or dependency types
 * (Repository, Request, Service) should have #[Hidden] attribute.
 */
class HiddenAttributeProphet extends PhpCommandment
{
    public function description(): string
    {
        return 'Add #[Hidden] to properties with #[FromContainer] or #[FromSession]';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Properties with injection attributes must have #[Hidden].

Properties with #[FromContainer] or #[FromSession] inject services or
session data that should never be sent to the frontend. Always add
#[Hidden] to prevent accidental exposure.

Bad:
    #[FromContainer(UserRepository::class)]
    public readonly UserRepository $repository;

Good:
    #[Hidden]
    #[FromContainer(UserRepository::class)]
    public readonly UserRepository $repository;
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        return PipelineBuilder::make(PhpContext::from($filePath, $content))
            ->returnRighteousWhen(fn (PhpContext $ctx) => ! $ctx->filePathContains('Http/View') && ! $ctx->filePathContains('Http\\View'))
            ->returnRighteousWhen(fn (PhpContext $ctx) => ! preg_match('/(Page|Data)\.php$/', $ctx->filePath))
            ->pipe(fn (PhpContext $ctx) => $this->findPropertiesMissingHidden($ctx))
            ->mapToSins(fn (PhpContext $ctx) => array_map(
                fn ($match) => $this->sinAt(
                    $match['line'],
                    "Property \${$match['name']} has injection attribute but missing #[Hidden]",
                    null,
                    'Add #[Hidden] attribute to prevent frontend exposure'
                ),
                $ctx->matches
            ))
            ->judge();
    }

    /**
     * Find properties with injection attributes but missing #[Hidden].
     */
    private function findPropertiesMissingHidden(PhpContext $ctx): PhpContext
    {
        $matches = [];

        // Find all public properties with their full attribute blocks
        preg_match_all(
            '/((#\[[^\]]+\]\s*)+)?\s*public\s+(?:readonly\s+)?([\w\\\\|]+)\s+\$(\w+)/s',
            $ctx->content,
            $rawMatches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        );

        foreach ($rawMatches as $match) {
            $attributeBlock = $match[1][0] ?? '';
            $propName = $match[4][0];
            $offset = $match[0][1];

            // Check if this property has #[FromContainer] or #[FromSession]
            $hasInjectionAttribute = str_contains($attributeBlock, '#[FromContainer')
                || str_contains($attributeBlock, '#[FromSession');

            if (! $hasInjectionAttribute) {
                continue;
            }

            // Check if it also has #[Hidden]
            if (str_contains($attributeBlock, '#[Hidden]')) {
                continue;
            }

            $matches[] = [
                'name' => $propName,
                'line' => substr_count(substr($ctx->content, 0, $offset), "\n") + 1,
            ];
        }

        return $ctx->with(matches: $matches);
    }
}
