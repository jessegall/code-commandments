<?php

namespace Symfony\Component\Console\Input;

interface InputInterface extends \Stringable
{
    public function getFirstArgument(): ?string;
    public function hasParameterOption(array|string $values, bool $onlyParams = false): bool;
    public function getParameterOption(array|string $values, array|string|int|float|bool|null $default = false, bool $onlyParams = false): mixed;
    public function bind(\Symfony\Component\Console\Input\InputDefinition $definition): void;
    public function validate(): void;
    public function getArguments(): array;
    public function getArgument(string $name): mixed;
    public function setArgument(string $name, mixed $value): void;
    public function hasArgument(string $name): bool;
    public function getOptions(): array;
    public function getOption(string $name): mixed;
    public function setOption(string $name, mixed $value): void;
    public function hasOption(string $name): bool;
    public function isInteractive(): bool;
    public function setInteractive(bool $interactive): void;
    public function __toString(): string;
}
