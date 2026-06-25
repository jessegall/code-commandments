<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\Pipes\Php\MatchPatterns;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpPipeline;

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
        return PhpPipeline::make($filePath, $content)
            ->onlyControllers()
            ->pipe((new MatchPatterns)->add('validated', '/\$(?:this->)?request->validated\(\)/'))
            ->sinsFromMatches(
                'Using $request->validated() returns untyped array',
                'Use typed getter methods on FormRequest instead'
            )
            ->judge();
    }
}
