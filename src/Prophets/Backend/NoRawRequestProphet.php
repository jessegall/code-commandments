<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use PhpParser\Node;
use ReflectionClass;

/**
 * Commandment: No raw Illuminate\Http\Request - Use dedicated FormRequest classes with typed getters.
 */
class NoRawRequestProphet extends PhpCommandment
{
    /** @var array<string, string> */
    private array $useStatements = [];

    private ?string $currentNamespace = null;

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
                // Skip constructor
                if ($method->name->toString() === '__construct') {
                    continue;
                }

                foreach ($method->params as $param) {
                    if ($param->type === null) {
                        continue;
                    }

                    $typeName = $this->getTypeName($param->type);

                    if ($typeName === null) {
                        continue;
                    }

                    $fqcn = $this->resolveFullyQualifiedName($typeName);

                    if ($this->isRawRequest($fqcn, $typeName)) {
                        $sins[] = $this->sinAt(
                            $method->getStartLine(),
                            sprintf('Raw Illuminate\Http\Request in method "%s"', $method->name->toString()),
                            sprintf('public function %s(%s $%s, ...)', $method->name->toString(), $typeName, $param->var->name),
                            'Use a dedicated FormRequest class with typed getter methods'
                        );
                    }
                }
            }
        }

        return empty($sins) ? $this->righteous() : $this->fallen($sins);
    }

    /**
     * Extract use statements from AST.
     *
     * @return array<string, string>
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

    private function resolveFullyQualifiedName(string $typeName): string
    {
        if (str_starts_with($typeName, '\\')) {
            return ltrim($typeName, '\\');
        }

        $parts = explode('\\', $typeName);
        $firstPart = $parts[0];

        if (isset($this->useStatements[$firstPart])) {
            if (count($parts) === 1) {
                return $this->useStatements[$firstPart];
            }
            $parts[0] = $this->useStatements[$firstPart];

            return implode('\\', $parts);
        }

        if ($this->currentNamespace) {
            return $this->currentNamespace.'\\'.$typeName;
        }

        return $typeName;
    }

    /**
     * Check if the type is raw Illuminate\Http\Request (not a FormRequest subclass).
     */
    private function isRawRequest(string $fqcn, string $typeName): bool
    {
        // Try reflection first
        if (class_exists($fqcn)) {
            try {
                $reflection = new ReflectionClass($fqcn);

                // It's raw request if it IS Illuminate\Http\Request
                // but NOT a subclass of FormRequest
                $isHttpRequest = $fqcn === 'Illuminate\\Http\\Request'
                    || $reflection->isSubclassOf('Illuminate\\Http\\Request')
                    || $reflection->getName() === 'Illuminate\\Http\\Request';

                $isFormRequest = $reflection->isSubclassOf('Illuminate\\Foundation\\Http\\FormRequest');

                return $isHttpRequest && !$isFormRequest;
            } catch (\ReflectionException) {
                // Fall through to string matching
            }
        }

        // Fall back to string matching
        // Raw Request is "Request" or "Illuminate\Http\Request"
        // FormRequest subclasses typically have names like "StoreUserRequest"
        $shortName = basename(str_replace('\\', '/', $fqcn));

        return $shortName === 'Request'
            || $fqcn === 'Illuminate\\Http\\Request';
    }
}
