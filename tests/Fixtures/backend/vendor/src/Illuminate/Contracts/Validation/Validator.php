<?php

namespace Illuminate\Contracts\Validation;

interface Validator extends \Illuminate\Contracts\Support\MessageProvider
{
    public function validate();
    public function validated();
    public function fails();
    public function failed();
    public function sometimes($attribute, $rules, callable $callback);
    public function after($callback);
    public function errors();
}
