<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\Pipes\Php\ExtractClasses;
use JesseGall\CodeCommandments\Support\Pipes\Php\FilterLaravelControllers;
use JesseGall\CodeCommandments\Support\Pipes\Php\MatchPatterns;
use JesseGall\CodeCommandments\Support\Pipes\Php\ParsePhpAst;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpContext;
use JesseGall\CodeCommandments\Support\Pipes\PipelineBuilder;

/**
 * Commandment: No $request->validated() in controllers - Use typed getters.
 */
class NoValidatedMethodProphet extends PhpCommandment
{
    public function description(): string
    {
        return 'Use typed getters instead of $request->validated()';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Never use $request->validated() in controllers.

The validated() method returns an untyped array, losing type safety.
Instead, use explicit typed getter methods on the FormRequest.

Bad:
    $data = $request->validated();
    $product->update($data);

Good:
    $product->name = $request->getName();
    $product->price = $request->getPrice();
    $product->save();
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        return PipelineBuilder::make(PhpContext::from($filePath, $content))
            ->pipe(ParsePhpAst::class)
            ->pipe(ExtractClasses::class)
            ->pipe(FilterLaravelControllers::class)
            ->returnRighteousIfNoClasses()
            ->pipe((new MatchPatterns)->add('validated', '/\$request->validated\(\)/'))
            ->sinsFromMatches(
                'Using $request->validated() returns untyped array',
                'Use typed getter methods on FormRequest instead'
            )
            ->judge();
    }
}
