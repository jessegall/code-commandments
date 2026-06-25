<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes\Php;

use JesseGall\CodeCommandments\Support\Pipes\Pipe;

/**
 * Extract all methods from classes.
 *
 * @implements Pipe<PhpContext, PhpContext>
 */
final class ExtractMethods implements Pipe
{
    private bool $onlyPublic = false;

    private bool $excludeConstructor = false;

    private bool $excludeMagic = false;

    public function onlyPublic(): self
    {
        $this->onlyPublic = true;

        return $this;
    }

    public function excludeConstructor(): self
    {
        $this->excludeConstructor = true;

        return $this;
    }

    public function excludeMagic(): self
    {
        $this->excludeMagic = true;

        return $this;
    }

    public function handle(mixed $input): mixed
    {
        $methods = [];

        foreach ($input->classes as $class) {
            foreach ($class->getMethods() as $method) {
                if ($this->onlyPublic && ! $method->isPublic()) {
                    continue;
                }

                $methodName = $method->name->toString();

                if ($this->excludeConstructor && $methodName === '__construct') {
                    continue;
                }

                if ($this->excludeMagic && str_starts_with($methodName, '__')) {
                    continue;
                }

                $methods[] = [
                    'class' => $class,
                    'method' => $method,
                ];
            }
        }

        return $input->with(methods: $methods);
    }
}
