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
    /** @return static */
    public static function withMessages(array $messages) {}
    /** @return string */
    protected static function summarize($validator) {}
    /** @return array */
    public function errors() {}
    /** @return $this */
    public function status($status) {}
    /** @return $this */
    public function errorBag($errorBag) {}
    /** @return $this */
    public function redirectTo($url) {}
    /** @return \Symfony\Component\HttpFoundation\Response|null */
    public function getResponse() {}
}
