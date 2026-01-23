<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes\Php;

use JesseGall\CodeCommandments\Support\Pipes\Pipe;
use PhpParser\Node;

/**
 * Extract typed parameters from methods.
 *
 * @implements Pipe<PhpContext, PhpContext>
 */
final class ExtractMethodParameters implements Pipe
{
    private bool $excludeScalars = true;

    public function includeScalars(): self
    {
        $this->excludeScalars = false;

        return $this;
    }

    public function handle(mixed $input): mixed
    {
        $parameters = [];

        foreach ($input->methods as $methodData) {
            $class = $methodData['class'];
            $method = $methodData['method'];

            foreach ($method->params as $param) {
                if ($param->type === null) {
                    continue;
                }

                $typeName = $this->getTypeName($param->type);

                if ($typeName === null) {
                    continue;
                }

                if ($this->excludeScalars && $this->isScalarType($typeName)) {
                    continue;
                }

                $fqcn = $this->resolveFullyQualifiedName($typeName, $input->useStatements, $input->namespace);

                $parameters[] = [
                    'class' => $class,
                    'method' => $method,
                    'param' => $param,
                    'type' => $typeName,
                    'fqcn' => $fqcn,
                    'name' => $param->var->name,
                ];
            }
        }

        return $input->with(parameters: $parameters);
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

        if ($type instanceof Node\UnionType || $type instanceof Node\IntersectionType) {
            // For union/intersection types, just get the first type
            return $this->getTypeName($type->types[0] ?? null);
        }

        return null;
    }

    private function isScalarType(string $typeName): bool
    {
        return in_array(strtolower($typeName), [
            'string', 'int', 'float', 'bool', 'array', 'object',
            'mixed', 'null', 'void', 'never', 'callable', 'iterable',
            'true', 'false',
        ], true);
    }

    /**
     * @param  array<string, string>  $useStatements
     */
    private function resolveFullyQualifiedName(string $typeName, array $useStatements, ?string $namespace): string
    {
        // Already fully qualified
        if (str_starts_with($typeName, '\\')) {
            return ltrim($typeName, '\\');
        }

        // Check use statements
        $parts = explode('\\', $typeName);
        $firstPart = $parts[0];

        if (isset($useStatements[$firstPart])) {
            if (count($parts) === 1) {
                return $useStatements[$firstPart];
            }

            $parts[0] = $useStatements[$firstPart];

            return implode('\\', $parts);
        }

        // Assume same namespace
        if ($namespace) {
            return $namespace.'\\'.$typeName;
        }

        return $typeName;
    }
}
