<?php

namespace Illuminate\Foundation\Http;

class FormRequest extends \Illuminate\Http\Request implements \Illuminate\Contracts\Validation\ValidatesWhenResolved
{
    use \Illuminate\Validation\ValidatesWhenResolvedTrait;
    protected $container = NULL;
    protected $redirector = NULL;
    protected $redirect = NULL;
    protected $redirectRoute = NULL;
    protected $redirectAction = NULL;
    protected $errorBag = 'default';
    protected $stopOnFirstFailure = false;
    protected $validator = NULL;
    protected function getValidatorInstance() {}
    protected function createDefaultValidator(\Illuminate\Contracts\Validation\Factory $factory) {}
    public function validationData() {}
    protected function validationRules() {}
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator) {}
    protected function getRedirectUrl() {}
    protected function passesAuthorization() {}
    protected function failedAuthorization() {}
    public function safe(?array $keys = NULL) {}
    public function validated($key = NULL, $default = NULL) {}
    public function messages() {}
    public function attributes() {}
    public function setValidator(\Illuminate\Contracts\Validation\Validator $validator) {}
    public function setRedirector(\Illuminate\Routing\Redirector $redirector) {}
    public function setContainer(\Illuminate\Contracts\Container\Container $container) {}
}
