<?php

namespace Symfony\Component\Console\Input;

class ArgvInput extends \Symfony\Component\Console\Input\Input
{
    private array $tokens;
    private array $parsed;
    public function __construct(?array $argv = NULL, ?\Symfony\Component\Console\Input\InputDefinition $definition = NULL) {}
    protected function setTokens(array $tokens): void {}
    protected function parse(): void {}
    protected function parseToken(string $token, bool $parseOptions): bool {}
    private function parseShortOption(string $token): void {}
    private function parseShortOptionSet(string $name): void {}
    private function parseLongOption(string $token): void {}
    private function parseArgument(string $token): void {}
    private function addShortOption(string $shortcut, mixed $value): void {}
    private function addLongOption(string $name, mixed $value): void {}
    public function getFirstArgument(): ?string {}
    public function hasParameterOption(array|string $values, bool $onlyParams = false): bool {}
    public function getParameterOption(array|string $values, array|string|int|float|bool|null $default = false, bool $onlyParams = false): mixed {}
    public function getRawTokens(bool $strip = false): array {}
    public function __toString(): string {}
}
