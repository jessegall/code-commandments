<?php

namespace Illuminate\Contracts\Validation;

interface Validator extends \Illuminate\Contracts\Support\MessageProvider
{
    /** @return array */
    public function validate();
    /** @return array */
    public function validated();
    /** @return bool */
    public function fails();
    /** @return array */
    public function failed();
    /** @return $this */
    public function sometimes($attribute, $rules, callable $callback);
    /** @return $this */
    public function after($callback);
    /** @return \Illuminate\Support\MessageBag */
    public function errors();
}
