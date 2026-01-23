<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use PhpParser\Node;
use ReflectionClass;

/**
 * Data classes must not have readonly properties with value-injecting attributes.
 *
 * Laravel Data needs to inject properties via attributes like WithCast or other
 * InjectsPropertyValue implementations, which doesn't work with readonly properties.
 */
class ReadonlyDataPropertiesProphet extends PhpCommandment
{
    private const INJECTS_PROPERTY_VALUE_INTERFACE = 'Spatie\\LaravelData\\Attributes\\InjectsPropertyValue';

    private const WITH_CAST_ATTRIBUTE = 'Spatie\\LaravelData\\Attributes\\WithCast';

    /** @var array<string, string> */
    private array $useStatements = [];

    private ?string $currentNamespace = null;

    public function description(): string
    {
        return 'Data classes must not have readonly properties with value-injecting attributes';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
In Laravel Data classes, readonly properties cannot be used with attributes
that inject values (like #[WithCast] or other InjectsPropertyValue implementations).

Readonly properties in class body are allowed, unless they have attributes
that implement InjectsPropertyValue or use #[WithCast].

Bad:
    class UserData extends Data
    {
        #[WithCast(DateTimeCast::class)]
        public readonly Carbon $createdAt;
    }

Good:
    class UserData extends Data
    {
        #[WithCast(DateTimeCast::class)]
        public Carbon $createdAt;
    }

Also good (readonly without injecting attributes):
    class UserData extends Data
    {
        public readonly string $name;
    }

Also good (readonly in constructor):
    class UserData extends Data
    {
        public function __construct(
            #[WithCast(DateTimeCast::class)]
            public readonly Carbon $createdAt,
        ) {}
    }
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if (!$ast || !$this->isLaravelClass($ast, 'data')) {
            return $this->righteous();
        }

        // Extract use statements and namespace for resolving FQCNs
        $this->useStatements = $this->extractUseStatements($ast);
        $this->currentNamespace = $this->getNamespace($ast);

        $sins = [];
        $classes = $this->findNodes($ast, Node\Stmt\Class_::class);

        foreach ($classes as $class) {
            foreach ($class->stmts as $stmt) {
                // Only check property declarations (not constructor-promoted properties)
                if (!$stmt instanceof Node\Stmt\Property) {
                    continue;
                }

                // Skip non-readonly properties
                if (!$stmt->isReadonly()) {
                    continue;
                }

                // Check if property has value-injecting attributes
                $injectingAttribute = $this->findInjectingAttribute($stmt);

                if ($injectingAttribute !== null) {
                    $propertyName = $stmt->props[0]->name->toString();
                    $sins[] = $this->sinAt(
                        $stmt->getStartLine(),
                        sprintf(
                            'Readonly property "$%s" has value-injecting attribute #[%s]',
                            $propertyName,
                            $injectingAttribute
                        ),
                        $this->getPropertyDeclaration($stmt),
                        'Remove the readonly modifier from the property'
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
     * Find a value-injecting attribute on the property.
     *
     * @return string|null The attribute name if found, null otherwise
     */
    private function findInjectingAttribute(Node\Stmt\Property $property): ?string
    {
        foreach ($property->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                $attributeName = $attr->name->toString();
                $fqcn = $this->resolveFullyQualifiedName($attributeName);

                // Check for WithCast directly
                if ($fqcn === self::WITH_CAST_ATTRIBUTE || $attributeName === 'WithCast') {
                    return $attributeName;
                }

                // Check if attribute implements InjectsPropertyValue
                if ($this->implementsInjectsPropertyValue($fqcn)) {
                    return $attributeName;
                }
            }
        }

        return null;
    }

    /**
     * Check if the attribute class implements InjectsPropertyValue.
     */
    private function implementsInjectsPropertyValue(string $fqcn): bool
    {
        if (!class_exists($fqcn)) {
            return false;
        }

        try {
            $reflection = new ReflectionClass($fqcn);

            return $reflection->implementsInterface(self::INJECTS_PROPERTY_VALUE_INTERFACE);
        } catch (\ReflectionException) {
            return false;
        }
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
            return $this->currentNamespace . '\\' . $typeName;
        }

        return $typeName;
    }

    /**
     * Get a string representation of the property declaration.
     */
    private function getPropertyDeclaration(Node\Stmt\Property $property): string
    {
        $parts = [];

        // Add attributes
        foreach ($property->attrGroups as $attrGroup) {
            $attrs = [];
            foreach ($attrGroup->attrs as $attr) {
                $attrs[] = $attr->name->toString();
            }
            $parts[] = '#[' . implode(', ', $attrs) . ']';
        }

        // Add visibility and readonly
        if ($property->isPublic()) {
            $parts[] = 'public';
        } elseif ($property->isProtected()) {
            $parts[] = 'protected';
        } elseif ($property->isPrivate()) {
            $parts[] = 'private';
        }

        if ($property->isReadonly()) {
            $parts[] = 'readonly';
        }

        // Add type if present
        if ($property->type !== null) {
            if ($property->type instanceof Node\Name) {
                $parts[] = $property->type->toString();
            } elseif ($property->type instanceof Node\Identifier) {
                $parts[] = $property->type->toString();
            }
        }

        // Add property name
        $parts[] = '$' . $property->props[0]->name->toString();

        return implode(' ', $parts);
    }
}
