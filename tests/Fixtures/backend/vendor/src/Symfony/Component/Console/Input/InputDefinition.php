<?php

namespace Symfony\Component\Console\Input;

class InputDefinition
{
    private array $arguments = array (
);
    private int $requiredCount = 0;
    private ?\Symfony\Component\Console\Input\InputArgument $lastArrayArgument = NULL;
    private ?\Symfony\Component\Console\Input\InputArgument $lastOptionalArgument = NULL;
    private array $options = array (
);
    private array $negations = array (
);
    private array $shortcuts = array (
);
    public function __construct(array $definition = array (
)) {}
    public function setDefinition(array $definition): void {}
    public function setArguments(array $arguments = array (
)): void {}
    public function addArguments(?array $arguments = array (
)): void {}
    public function addArgument(\Symfony\Component\Console\Input\InputArgument $argument): void {}
    public function getArgument(string|int $name): \Symfony\Component\Console\Input\InputArgument {}
    public function hasArgument(string|int $name): bool {}
    public function getArguments(): array {}
    public function getArgumentCount(): int {}
    public function getArgumentRequiredCount(): int {}
    public function getArgumentDefaults(): array {}
    public function setOptions(array $options = array (
)): void {}
    public function addOptions(array $options = array (
)): void {}
    public function addOption(\Symfony\Component\Console\Input\InputOption $option): void {}
    public function getOption(string $name): \Symfony\Component\Console\Input\InputOption {}
    public function hasOption(string $name): bool {}
    public function getOptions(): array {}
    public function hasShortcut(string $name): bool {}
    public function hasNegation(string $name): bool {}
    public function getOptionForShortcut(string $shortcut): \Symfony\Component\Console\Input\InputOption {}
    public function getOptionDefaults(): array {}
    public function shortcutToName(string $shortcut): string {}
    public function negationToName(string $negation): string {}
    public function getSynopsis(bool $short = false): string {}
}
