<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Sin;
use JesseGall\CodeCommandments\Support\Pipes\Php\ExtractClass;
use JesseGall\CodeCommandments\Support\Pipes\Php\ExtractMethods;
use JesseGall\CodeCommandments\Support\Pipes\Php\FilterLongMethod;
use JesseGall\CodeCommandments\Support\Pipes\Php\MethodLineCounter;
use JesseGall\CodeCommandments\Support\Pipes\Php\ParsePhpAst;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpContext;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpPipeline;
use PhpParser\Node\Scalar\InterpolatedString;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;

/**
 * Methods should not be excessively long.
 *
 * Long methods are a sign that the method is doing too many things
 * and should be broken down into smaller, well-named methods.
 *
 *
 *
 *
 * @method-generated-start
 * @method static maxMethodLines(int $value)
 * @method-generated-end
 */
#[IntroducedIn('1.3.1')]
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

            // A method whose entire body is `return <heredoc/nowdoc/string>;` is
            // verbatim CONTENT (skill docs, a template, an SQL blob) — its line count
            // is authored text, not logic to extract (#206). Never flag it.
            if ($this->isVerbatimContent($method)) {
                continue;
            }

            $class = $methodData['class'];
            $className = $class->name?->toString() ?? 'Unknown';
            $methodName = $method->name->toString();
            $lineCount = MethodLineCounter::count(
                $ctx->content,
                $method->getStartLine(),
                $method->getEndLine()
            );

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

    /**
     * Whether the method's whole body is a single `return <string literal>;` — a
     * heredoc/nowdoc/plain or interpolated string. Such a method holds verbatim
     * content; its length is text, not branching logic to break up.
     */
    private function isVerbatimContent(ClassMethod $method): bool
    {
        if ($method->stmts === null || count($method->stmts) !== 1) {
            return false;
        }

        $statement = $method->stmts[0];

        return $statement instanceof Return_
            && ($statement->expr instanceof String_ || $statement->expr instanceof InterpolatedString);
    }
}
