<?php

namespace Illuminate\Contracts\Validation;

interface Factory
{
    /** @return \Illuminate\Contracts\Validation\Validator */
    public function make(array $data, array $rules, array $messages = array (
), array $attributes = array (
));
    /** @return void */
    public function extend($rule, $extension, $message = NULL);
    /** @return void */
    public function extendImplicit($rule, $extension, $message = NULL);
    /** @return void */
    public function replacer($rule, $replacer);
}
