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
    /** @return \Illuminate\Contracts\Validation\Validator */
    protected function getValidatorInstance() {}
    /** @return \Illuminate\Contracts\Validation\Validator */
    protected function createDefaultValidator(\Illuminate\Contracts\Validation\Factory $factory) {}
    /** @return array */
    public function validationData() {}
    /** @return array */
    protected function validationRules() {}
    /** @return void */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator) {}
    /** @return string */
    protected function getRedirectUrl() {}
    /** @return bool */
    protected function passesAuthorization() {}
    /** @return void */
    protected function failedAuthorization() {}
    /** @return \Illuminate\Support\ValidatedInput|array */
    public function safe(?array $keys = NULL) {}
    /** @return mixed */
    public function validated($key = NULL, $default = NULL) {}
    /** @return array<string, */
    public function messages() {}
    /** @return array<string, */
    public function attributes() {}
    /** @return $this */
    public function setValidator(\Illuminate\Contracts\Validation\Validator $validator) {}
    /** @return $this */
    public function setRedirector(\Illuminate\Routing\Redirector $redirector) {}
    /** @return $this */
    public function setContainer(\Illuminate\Contracts\Container\Container $container) {}
}
