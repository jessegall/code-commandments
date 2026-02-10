<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Sin;
use JesseGall\CodeCommandments\Support\Pipes\Php\ExtractClass;
use JesseGall\CodeCommandments\Support\Pipes\Php\ExtractMethods;
use JesseGall\CodeCommandments\Support\Pipes\Php\FilterLongMethod;
use JesseGall\CodeCommandments\Support\Pipes\Php\ParsePhpAst;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpContext;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpPipeline;

/**
 * Methods should not be excessively long.
 *
 * Long methods are a sign that the method is doing too many things
 * and should be broken down into smaller, well-named methods.
 */
class LongMethodProphet extends PhpCommandment
{
    public function description(): string
    {
        return 'Keep methods short and focused on a single responsibility';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Methods should be short and focused on doing one thing well.
When a method grows too long, it typically means it's handling
multiple responsibilities that should be extracted.

Extract logic into:
- Smaller, well-named private methods
- Dedicated service or action classes
- Value objects that encapsulate related operations

Bad:
    public function store(Request $request)
    {
        // 50+ lines of validation, entity resolution,
        // branching logic, notifications, logging...
    }

Good:
    public function store(StoreRequest $request, CreateOrderAction $action)
    {
        $order = $action->execute($request->validated());

        return new OrderResource($order);
    }
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $maxLines = (int) $this->config('max_method_lines', 20);

        return PhpPipeline::make($filePath, $content)
            ->pipe(ParsePhpAst::class)
            ->pipe(ExtractClass::class)
            ->returnRighteousIfNoClass()
            ->pipe((new ExtractMethods)->excludeConstructor())
            ->pipe((new FilterLongMethod)->withMaxLines($maxLines))
            ->returnRighteousWhen(fn (PhpContext $ctx) => empty($ctx->methods))
            ->mapToSins(fn (PhpContext $ctx) => $this->createSins($ctx, $maxLines))
            ->judge();
    }

    /**
     * Create a sin for each long method.
     *
     * @return array<Sin>
     */
    private function createSins(PhpContext $ctx, int $maxLines): array
    {
        $sins = [];

        foreach ($ctx->methods as $methodData) {
            $method = $methodData['method'];
            $class = $methodData['class'];
            $className = $class->name?->toString() ?? 'Unknown';
            $methodName = $method->name->toString();
            $lineCount = $method->getEndLine() - $method->getStartLine() + 1;

            $sins[] = $this->sinAt(
                $method->getStartLine(),
                sprintf(
                    '%s::%s() is %d lines long (max: %d)',
                    $className,
                    $methodName,
                    $lineCount,
                    $maxLines
                ),
                null,
                'Extract logic into smaller, well-named private methods or dedicated classes'
            );
        }

        return $sins;
    }
}
