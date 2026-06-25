<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes\Php;

use JesseGall\CodeCommandments\Support\CallGraph\NameResolver;
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

                $typeName = NameResolver::typeName($param->type);

                if ($typeName === null) {
                    continue;
                }

                if ($this->excludeScalars && $this->isScalarType($typeName)) {
                    continue;
                }

                $fqcn = NameResolver::resolve($typeName, $input->useStatements, $input->namespace);

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

    private function isScalarType(string $typeName): bool
    {
        return in_array(strtolower($typeName), [
            'string', 'int', 'float', 'bool', 'array', 'object',
            'mixed', 'null', 'void', 'never', 'callable', 'iterable',
            'true', 'false',
        ], true);
    }
}
