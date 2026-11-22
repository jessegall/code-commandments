<?php

namespace Symfony\Component\Console\Completion;

final class CompletionInput extends \Symfony\Component\Console\Input\ArgvInput
{
    public const TYPE_ARGUMENT_VALUE = 'argument_value';
    public const TYPE_OPTION_VALUE = 'option_value';
    public const TYPE_OPTION_NAME = 'option_name';
    public const TYPE_NONE = 'none';
    private array $tokens;
    private int $currentIndex;
    private string $completionType;
    private ?string $completionName = NULL;
    private string $completionValue = '';
    public static function fromString(string $inputStr, int $currentIndex): \Symfony\Component\Console\Completion\CompletionInput {}
    public static function fromTokens(array $tokens, int $currentIndex): \Symfony\Component\Console\Completion\CompletionInput {}
    public function bind(\Symfony\Component\Console\Input\InputDefinition $definition): void {}
    public function getCompletionType(): string {}
    public function getCompletionName(): ?string {}
    public function getCompletionValue(): string {}
    public function mustSuggestOptionValuesFor(string $optionName): bool {}
    public function mustSuggestArgumentValuesFor(string $argumentName): bool {}
    protected function parseToken(string $token, bool $parseOptions): bool {}
    private function getOptionFromToken(string $optionToken): ?\Symfony\Component\Console\Input\InputOption {}
    private function getRelevantToken(): string {}
    private function isCursorFree(): bool {}
    public function __toString(): string {}
}
