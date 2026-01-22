<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use PhpParser\Node;

/**
 * Controller dependencies should be injected via constructor, not methods.
 *
 * Method injection makes dependencies hidden and harder to test.
 * Use constructor injection for services, repositories, and other dependencies.
 * Request objects and route model binding are exceptions.
 */
class ConstructorDependencyInjectionProphet extends PhpCommandment
{
    /**
     * Type suffixes that indicate a service/dependency that should be constructor-injected.
     */
    private const DEPENDENCY_SUFFIXES = [
        'Service',
        'Repository',
        'Factory',
        'Builder',
        'Handler',
        'Manager',
        'Provider',
        'Client',
        'Gateway',
        'Adapter',
        'Helper',
        'Processor',
        'Validator',
        'Resolver',
        'Generator',
        'Writer',
        'Reader',
        'Parser',
        'Formatter',
        'Transformer',
        'Calculator',
        'Notifier',
        'Dispatcher',
        'Registry',
        'Cache',
        'Logger',
        'Mailer',
    ];

    /**
     * Types that are allowed in method signatures (not dependencies).
     */
    private const ALLOWED_METHOD_TYPES = [
        'Request',
        'FormRequest',
        'ServerRequest',
    ];

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
        $ast = $this->parse($content);

        if (!$ast || !$this->isLaravelClass($ast, 'controller')) {
            return $this->righteous();
        }

        $sins = [];
        $classes = $this->findNodes($ast, Node\Stmt\Class_::class);

        foreach ($classes as $class) {
            foreach ($class->getMethods() as $method) {
                // Skip constructor and non-public methods
                if ($method->name->toString() === '__construct' || !$method->isPublic()) {
                    continue;
                }

                $dependencies = $this->findMethodDependencies($method);

                foreach ($dependencies as $dependency) {
                    $sins[] = $this->sinAt(
                        $method->getStartLine(),
                        sprintf(
                            'Method "%s" has dependency "%s" injected - move to constructor',
                            $method->name->toString(),
                            $dependency['type']
                        ),
                        sprintf('public function %s(..., %s $%s, ...)', $method->name->toString(), $dependency['type'], $dependency['name']),
                        sprintf('Inject %s via constructor: __construct(private %s $%s)', $dependency['type'], $dependency['type'], $dependency['name'])
                    );
                }
            }
        }

        return empty($sins) ? $this->righteous() : $this->fallen($sins);
    }

    /**
     * Find dependencies in method parameters that should be constructor-injected.
     *
     * @return array<array{type: string, name: string}>
     */
    private function findMethodDependencies(Node\Stmt\ClassMethod $method): array
    {
        $dependencies = [];

        foreach ($method->params as $param) {
            if ($param->type === null) {
                continue;
            }

            $typeName = $this->getTypeName($param->type);

            if ($typeName === null) {
                continue;
            }

            // Skip allowed types (Request, FormRequest, etc.)
            if ($this->isAllowedMethodType($typeName)) {
                continue;
            }

            // Skip likely route model binding (models don't typically end with dependency suffixes)
            if ($this->isLikelyModelBinding($typeName)) {
                continue;
            }

            // Check if it looks like a dependency (Service, Repository, etc.)
            if ($this->isDependencyType($typeName)) {
                $dependencies[] = [
                    'type' => $typeName,
                    'name' => $param->var->name,
                ];
            }
        }

        return $dependencies;
    }

    private function getTypeName(?Node $type): ?string
    {
        if ($type instanceof Node\Name) {
            return $type->toString();
        }

        if ($type instanceof Node\Identifier) {
            return $type->toString();
        }

        if ($type instanceof Node\NullableType) {
            return $this->getTypeName($type->type);
        }

        return null;
    }

    private function isAllowedMethodType(string $typeName): bool
    {
        $shortName = $this->getShortClassName($typeName);

        foreach (self::ALLOWED_METHOD_TYPES as $allowed) {
            if ($shortName === $allowed || str_ends_with($shortName, $allowed)) {
                return true;
            }
        }

        return false;
    }

    private function isLikelyModelBinding(string $typeName): bool
    {
        $shortName = $this->getShortClassName($typeName);

        // If it doesn't end with a dependency suffix, it's likely a model
        // Models are typically simple names like User, Order, Product
        return !$this->isDependencyType($typeName);
    }

    private function isDependencyType(string $typeName): bool
    {
        $shortName = $this->getShortClassName($typeName);

        foreach (self::DEPENDENCY_SUFFIXES as $suffix) {
            if (str_ends_with($shortName, $suffix)) {
                return true;
            }
        }

        return false;
    }

    private function getShortClassName(string $typeName): string
    {
        $parts = explode('\\', $typeName);

        return end($parts);
    }
}
