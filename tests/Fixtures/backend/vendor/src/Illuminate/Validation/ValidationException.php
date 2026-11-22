<?php

namespace Illuminate\Validation;

class ValidationException extends \Exception
{
    public $validator = NULL;
    public $response = NULL;
    public $status = 422;
    public $errorBag = NULL;
    public $redirectTo = NULL;
    public function __construct($validator, $response = NULL, $errorBag = 'default') {}
    public static function withMessages(array $messages) {}
    protected static function summarize($validator) {}
    public function errors() {}
    public function status($status) {}
    public function errorBag($errorBag) {}
    public function redirectTo($url) {}
    public function getResponse() {}
}
