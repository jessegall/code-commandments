<?php

namespace Illuminate\Validation;

trait ValidatesWhenResolvedTrait
{
    public function validateResolved() {}
    protected function prepareForValidation() {}
    protected function getValidatorInstance() {}
    protected function passedValidation() {}
    protected function failedValidation(\Illuminate\Validation\Validator $validator) {}
    protected function passesAuthorization() {}
    protected function failedAuthorization() {}
}
