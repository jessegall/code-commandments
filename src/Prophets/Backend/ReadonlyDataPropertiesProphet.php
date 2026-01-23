<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\Pipes\MatchResult;
use JesseGall\CodeCommandments\Support\Pipes\Php\ExtractClasses;
use JesseGall\CodeCommandments\Support\Pipes\Php\ExtractUseStatements;
use JesseGall\CodeCommandments\Support\Pipes\Php\FilterLaravelDataClasses;
use JesseGall\CodeCommandments\Support\Pipes\Php\ParsePhpAst;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpContext;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpPipeline;
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
        return PhpPipeline::make($filePath, $content)
            ->pipe(ParsePhpAst::class)
            ->pipe(ExtractClasses::class)
            ->pipe(FilterLaravelDataClasses::class)
            ->returnRighteousIfNoClasses()
            ->pipe(ExtractUseStatements::class)
            ->pipe(fn (PhpContext $ctx) => $this->findReadonlyPropertiesWithInjectingAttributes($ctx))
            ->mapToSins(fn (PhpContext $ctx) => array_map(
                fn (MatchResult $match) => $this->sinAt(
                    $match->line,
                    sprintf(
                        'Readonly property "$%s" has value-injecting attribute #[%s]',
                        $match->groups['property'],
                        $match->groups['attribute']
                    ),
                    $match->groups['declaration'],
                    'Remove the readonly modifier from the property'
                ),
                $ctx->matches
            ))
            ->judge();
    }

    private function findReadonlyPropertiesWithInjectingAttributes(PhpContext $ctx): PhpContext
    {
        $matches = [];

        foreach ($ctx->classes as $class) {
            foreach ($class->stmts as $stmt) {
                // Only check property declarations (not constructor-promoted properties)
                if (! $stmt instanceof Node\Stmt\Property) {
                    continue;
                }

                // Skip non-readonly properties
                if (! $stmt->isReadonly()) {
                    continue;
                }

                // Check if property has value-injecting attributes
                $injectingAttribute = $this->findInjectingAttribute($stmt, $ctx);

                if ($injectingAttribute !== null) {
                    $matches[] = new MatchResult(
                        name: 'readonly_data_property',
                        pattern: '',
                        match: '',
                        line: $stmt->getStartLine(),
                        offset: null,
                        content: null,
                        groups: [
                            'property' => $stmt->props[0]->name->toString(),
                            'attribute' => $injectingAttribute,
                            'declaration' => $this->getPropertyDeclaration($stmt),
                        ],
                    );
                }
            }
        }

        return $ctx->with(matches: $matches);
    }

    /**
     * Find a value-injecting attribute on the property.
     *
     * @return string|null The attribute name if found, null otherwise
     */
    private function findInjectingAttribute(Node\Stmt\Property $property, PhpContext $ctx): ?string
    {
        foreach ($property->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                $attributeName = $attr->name->toString();
                $fqcn = $this->resolveFullyQualifiedName($attributeName, $ctx);

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
        if (! class_exists($fqcn)) {
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
    private function resolveFullyQualifiedName(string $typeName, PhpContext $ctx): string
    {
        // Already fully qualified
        if (str_starts_with($typeName, '\\')) {
            return ltrim($typeName, '\\');
        }

        // Check use statements
        $parts = explode('\\', $typeName);
        $firstPart = $parts[0];

        if (isset($ctx->useStatements[$firstPart])) {
            if (count($parts) === 1) {
                return $ctx->useStatements[$firstPart];
            }
            // Partial match - replace first part with use statement
            $parts[0] = $ctx->useStatements[$firstPart];

            return implode('\\', $parts);
        }

        // Assume same namespace
        if ($ctx->namespace) {
            return $ctx->namespace.'\\'. $typeName;
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
            $parts[] = '#['.implode(', ', $attrs).']';
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
        $parts[] = '$'.$property->props[0]->name->toString();

        return implode(' ', $parts);
    }
}
