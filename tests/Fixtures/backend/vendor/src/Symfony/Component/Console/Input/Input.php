<?php

namespace Symfony\Component\Console\Input;

abstract class Input implements \Symfony\Component\Console\Input\InputInterface, \Symfony\Component\Console\Input\StreamableInputInterface, \Stringable
{
    protected \Symfony\Component\Console\Input\InputDefinition $definition;
    protected $stream = NULL;
    protected array $options = array (
);
    protected array $arguments = array (
);
    protected bool $interactive = true;
    public function __construct(?\Symfony\Component\Console\Input\InputDefinition $definition = NULL) {}
    public function bind(\Symfony\Component\Console\Input\InputDefinition $definition): void {}
    abstract protected function parse(): void;
    public function validate(): void {}
    public function isInteractive(): bool {}
    public function setInteractive(bool $interactive): void {}
    public function getArguments(): array {}
    public function getArgument(string $name): mixed {}
    public function setArgument(string $name, mixed $value): void {}
    public function hasArgument(string $name): bool {}
    public function getOptions(): array {}
    public function getOption(string $name): mixed {}
    public function setOption(string $name, mixed $value): void {}
    public function hasOption(string $name): bool {}
    public function escapeToken(string $token): string {}
    public function setStream($stream): void {}
    public function getStream() {}
}
