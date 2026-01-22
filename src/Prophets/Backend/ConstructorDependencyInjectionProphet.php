<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use PhpParser\Node;
use ReflectionClass;

/**
 * Controller dependencies should be injected via constructor, not methods.
 *
 * Method injection makes dependencies hidden and harder to test.
 * Use constructor injection for services, repositories, and other dependencies.
 * Request objects and route model binding are exceptions.
 */
class ConstructorDependencyInjectionProphet extends PhpCommandment
{
    /** @var array<string, string> */
    private array $useStatements = [];

    private ?string $currentNamespace = null;

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

        // Extract use statements and namespace for resolving FQCNs
        $this->useStatements = $this->extractUseStatements($ast);
        $this->currentNamespace = $this->getNamespace($ast);

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
     * Extract use statements from AST.
     *
     * @return array<string, string> Short name => FQCN
     */
    private function extractUseStatements(array $ast): array
    {
        $uses = [];

        foreach ($ast as $node) {
            if ($node instanceof Node\Stmt\Namespace_) {
                foreach ($node->stmts as $stmt) {
                    if ($stmt instanceof Node\Stmt\Use_) {
                        foreach ($stmt->uses as $use) {
                            $fqcn = $use->name->toString();
                            $alias = $use->alias?->toString() ?? $use->name->getLast();
                            $uses[$alias] = $fqcn;
                        }
                    }
                }
            } elseif ($node instanceof Node\Stmt\Use_) {
                foreach ($node->uses as $use) {
                    $fqcn = $use->name->toString();
                    $alias = $use->alias?->toString() ?? $use->name->getLast();
                    $uses[$alias] = $fqcn;
                }
            }
        }

        return $uses;
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

            // Skip scalar types
            if ($this->isScalarType($typeName)) {
                continue;
            }

            // Resolve to FQCN
            $fqcn = $this->resolveFullyQualifiedName($typeName);

            // Skip if it's a Request or Model
            if ($this->isRequestType($fqcn) || $this->isModelType($fqcn)) {
                continue;
            }

            // It's a dependency that should be constructor-injected
            $dependencies[] = [
                'type' => $typeName,
                'name' => $param->var->name,
            ];
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

    private function isScalarType(string $typeName): bool
    {
        return in_array(strtolower($typeName), [
            'string', 'int', 'float', 'bool', 'array', 'object',
            'mixed', 'null', 'void', 'never', 'callable', 'iterable',
        ], true);
    }

    /**
     * Resolve a type name to its fully qualified class name.
     */
    private function resolveFullyQualifiedName(string $typeName): string
    {
        // Already fully qualified
        if (str_starts_with($typeName, '\\')) {
            return ltrim($typeName, '\\');
        }

        // Check use statements
        $parts = explode('\\', $typeName);
        $firstPart = $parts[0];

        if (isset($this->useStatements[$firstPart])) {
            if (count($parts) === 1) {
                return $this->useStatements[$firstPart];
            }
            // Partial match - replace first part with use statement
            $parts[0] = $this->useStatements[$firstPart];

            return implode('\\', $parts);
        }

        // Assume same namespace
        if ($this->currentNamespace) {
            return $this->currentNamespace.'\\'.$typeName;
        }

        return $typeName;
    }

    /**
     * Check if the type is a Request type (allowed in methods).
     */
    private function isRequestType(string $fqcn): bool
    {
        if (!class_exists($fqcn)) {
            // Fall back to name-based check if class doesn't exist
            $shortName = $this->getShortClassName($fqcn);

            return str_ends_with($shortName, 'Request');
        }

        try {
            $reflection = new ReflectionClass($fqcn);

            // Check if it's a Request or FormRequest
            return $reflection->isSubclassOf('Illuminate\\Http\\Request')
                || $reflection->getName() === 'Illuminate\\Http\\Request';
        } catch (\ReflectionException) {
            return false;
        }
    }

    /**
     * Check if the type is an Eloquent Model (allowed in methods for route model binding).
     */
    private function isModelType(string $fqcn): bool
    {
        if (!class_exists($fqcn)) {
            // Can't determine - assume it's NOT a model (safer to flag it)
            return false;
        }

        try {
            $reflection = new ReflectionClass($fqcn);

            return $fqcn === 'Illuminate\\Database\\Eloquent\\Model'
                || $reflection->isSubclassOf('Illuminate\\Database\\Eloquent\\Model');
        } catch (\ReflectionException) {
            return false;
        }
    }

    private function getShortClassName(string $typeName): string
    {
        $parts = explode('\\', $typeName);

        return end($parts);
    }
}
