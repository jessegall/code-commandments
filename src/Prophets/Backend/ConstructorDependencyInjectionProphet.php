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
use JesseGall\CodeCommandments\Support\Pipes\Php\FilterServiceTypes;
use JesseGall\CodeCommandments\Support\Pipes\Php\ParsePhpAst;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpContext;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpPipeline;

/**
 * Controller dependencies should be injected via constructor, not methods.
 *
 * Method injection makes dependencies hidden and harder to test.
 * Use constructor injection for services, repositories, and other dependencies.
 * Request objects and route model binding are exceptions.
 */
class ConstructorDependencyInjectionProphet extends PhpCommandment
{
    public function description(): string
    {
        return 'Controller dependencies should be injected via constructor';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Dependencies like services, repositories, and handlers should be injected
via the constructor, not as method parameters. Method injection hides
dependencies and makes the class harder to understand and test.

Exceptions (allowed in methods):
- Request/FormRequest objects (designed for method injection)
- Route model binding (Eloquent models resolved from route parameters)
- Enums (simple value objects, not services)

Bad:
    class UserController extends Controller
    {
        public function store(StoreUserRequest $request, UserService $service)
        {
            return $service->create($request->validated());
        }
    }

Good:
    class UserController extends Controller
    {
        public function __construct(
            private UserService $service,
        ) {}

        public function store(StoreUserRequest $request)
        {
            return $this->service->create($request->validated());
        }
    }
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        return PhpPipeline::make($filePath, $content)
            ->pipe(ParsePhpAst::class)
            ->pipe(ExtractClasses::class)
            ->pipe(FilterLaravelControllers::class)
            ->returnRighteousIfNoAstOrClasses()
            ->pipe(ExtractUseStatements::class)
            ->pipe((new ExtractMethods)->onlyPublic()->excludeConstructor())
            ->pipe(ExtractMethodParameters::class)
            ->pipe(FilterServiceTypes::class)
            ->mapToSins(fn (PhpContext $ctx) => array_map(
                fn ($param) => $this->createSin($param),
                $ctx->parameters
            ))
            ->judge();
    }

    /**
     * Create a sin for a method parameter that should be constructor-injected.
     */
    private function createSin(array $param): \JesseGall\CodeCommandments\Results\Sin
    {
        $methodName = $param['method']->name->toString();
        $typeName = $param['type'];
        $paramName = $param['name'];

        return $this->sinAt(
            $param['method']->getStartLine(),
            sprintf('Method "%s" has dependency "%s" injected - move to constructor', $methodName, $typeName),
            sprintf('public function %s(..., %s $%s, ...)', $methodName, $typeName, $paramName),
            sprintf('Inject %s via constructor: __construct(private %s $%s)', $typeName, $typeName, $paramName)
        );
    }
}
