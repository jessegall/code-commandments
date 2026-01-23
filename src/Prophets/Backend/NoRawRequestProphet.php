<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\Pipes\Php\ExtractClasses;
use JesseGall\CodeCommandments\Support\Pipes\Php\ExtractMethodParameters;
use JesseGall\CodeCommandments\Support\Pipes\Php\ExtractMethods;
use JesseGall\CodeCommandments\Support\Pipes\Php\ExtractUseStatements;
use JesseGall\CodeCommandments\Support\Pipes\Php\FilterLaravelControllers;
use JesseGall\CodeCommandments\Support\Pipes\Php\FilterRawRequestParameters;
use JesseGall\CodeCommandments\Support\Pipes\Php\ParsePhpAst;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpContext;
use JesseGall\CodeCommandments\Support\Pipes\PipelineBuilder;

/**
 * Commandment: No raw Illuminate\Http\Request - Use dedicated FormRequest classes with typed getters.
 */
class NoRawRequestProphet extends PhpCommandment
{
    public function description(): string
    {
        return 'Use FormRequest classes instead of raw Request in controllers';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Never use raw Illuminate\Http\Request in controller methods.

Instead, create a dedicated FormRequest class with typed getter methods.
This ensures validation is handled before the controller, and provides
type-safe access to request data.

Bad:
    public function store(Request $request) {
        $name = $request->input('name');
    }

Good:
    public function store(StoreProductRequest $request) {
        $name = $request->getName();
    }
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        return PipelineBuilder::make(PhpContext::from($filePath, $content))
            ->pipe(ParsePhpAst::class)
            ->pipe(ExtractClasses::class)
            ->pipe(FilterLaravelControllers::class)
            ->returnRighteousIfNoClasses()
            ->pipe(ExtractUseStatements::class)
            ->pipe((new ExtractMethods)->excludeConstructor())
            ->pipe(ExtractMethodParameters::class)
            ->pipe(FilterRawRequestParameters::class)
            ->mapToSins(fn (PhpContext $ctx) => array_map(
                fn ($param) => $this->sinAt(
                    $param['method']->getStartLine(),
                    sprintf('Raw Illuminate\Http\Request in method "%s"', $param['method']->name->toString()),
                    sprintf('public function %s(%s $%s, ...)', $param['method']->name->toString(), $param['type'], $param['name']),
                    'Use a dedicated FormRequest class with typed getter methods'
                ),
                $ctx->parameters
            ))
            ->judge();
    }
}
