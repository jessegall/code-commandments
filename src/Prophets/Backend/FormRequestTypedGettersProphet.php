<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\Pipes\MatchResult;
use JesseGall\CodeCommandments\Support\Pipes\Php\ExtractClasses;
use JesseGall\CodeCommandments\Support\Pipes\Php\FilterFormRequestClasses;
use JesseGall\CodeCommandments\Support\Pipes\Php\MatchPatterns;
use JesseGall\CodeCommandments\Support\Pipes\Php\ParsePhpAst;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpContext;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpPipeline;

/**
 * Commandment: FormRequest getters must have explicit return types.
 */
class FormRequestTypedGettersProphet extends PhpCommandment
{
    public function description(): string
    {
        return 'Add explicit return types to FormRequest getter methods';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
All FormRequest getter methods must have explicit return types.

This ensures type safety when accessing request data in controllers.
Getter methods should follow the pattern: getPropertyName(): Type

Bad:
    public function getName() {
        return $this->string('name');
    }

Good:
    public function getName(): string {
        return $this->string('name')->toString();
    }

    public function getWeight(): ?int {
        return $this->integer('weight');
    }
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        // Pattern matches getter methods without return types
        // Matches: public function getName() or public function getName()  {
        // Does NOT match: public function getName(): Type
        $pattern = '/public\s+function\s+(get\w+)\s*\([^)]*\)(?!\s*:)/';

        return PhpPipeline::make($filePath, $content)
            ->pipe(ParsePhpAst::class)
            ->pipe(ExtractClasses::class)
            ->pipe(FilterFormRequestClasses::class)
            ->returnRighteousIfNoClasses()
            ->pipe((new MatchPatterns)->add('getter_no_return', $pattern))
            ->mapToWarnings(fn (PhpContext $ctx) => $this->createWarnings($ctx))
            ->judge();
    }

    /**
     * Create warnings for methods missing return types.
     *
     * @return array<\JesseGall\CodeCommandments\Results\Warning>
     */
    private function createWarnings(PhpContext $ctx): array
    {
        if (empty($ctx->matches)) {
            return [];
        }

        return array_map(
            fn (MatchResult $match) => $this->warningAt(
                $match->line,
                "Method {$match->groups[1]}() missing return type",
                "Add explicit return type: public function {$match->groups[1]}(): Type"
            ),
            $ctx->matches
        );
    }
}
