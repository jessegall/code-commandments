<?php

namespace Illuminate\Console\Concerns;

trait ConfiguresPrompts
{
    protected function configurePrompts(\Symfony\Component\Console\Input\InputInterface $input) {}
    protected function promptUntilValid($prompt, $required, $validate) {}
    protected function validatePrompt($value, $rules) {}
    protected function getPromptValidatorInstance($field, $value, $rules, array $messages = array (
), array $attributes = array (
)) {}
    protected function validationMessages() {}
    protected function validationAttributes() {}
    protected function restorePrompts() {}
    private function selectFallback($label, $options, $default = NULL) {}
    private function multiselectFallback($label, $options, $default = array (
), $required = false) {}
}
