<?php

namespace Illuminate\Console\Concerns;

trait ConfiguresPrompts
{
    /** @return void */
    protected function configurePrompts(\Symfony\Component\Console\Input\InputInterface $input) {}
    /** @return PResult */
    protected function promptUntilValid($prompt, $required, $validate) {}
    /** @return ?string */
    protected function validatePrompt($value, $rules) {}
    /** @return \Illuminate\Validation\Validator */
    protected function getPromptValidatorInstance($field, $value, $rules, array $messages = array (
), array $attributes = array (
)) {}
    /** @return array<string, */
    protected function validationMessages() {}
    /** @return array<string, */
    protected function validationAttributes() {}
    /** @return void */
    protected function restorePrompts() {}
    /** @return string|int */
    private function selectFallback($label, $options, $default = NULL) {}
    /** @return array */
    private function multiselectFallback($label, $options, $default = array (
), $required = false) {}
}
