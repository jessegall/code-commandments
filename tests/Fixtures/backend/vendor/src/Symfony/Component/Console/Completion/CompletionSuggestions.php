<?php

namespace Symfony\Component\Console\Completion;

final class CompletionSuggestions
{
    private array $valueSuggestions = array (
);
    private array $optionSuggestions = array (
);
    public function suggestValue(\Symfony\Component\Console\Completion\Suggestion|string $value): static {}
    public function suggestValues(array $values): static {}
    public function suggestOption(\Symfony\Component\Console\Input\InputOption $option): static {}
    public function suggestOptions(array $options): static {}
    public function getOptionSuggestions(): array {}
    public function getValueSuggestions(): array {}
}
