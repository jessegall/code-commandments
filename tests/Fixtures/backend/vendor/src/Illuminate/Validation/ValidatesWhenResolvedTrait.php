<?php

namespace Illuminate\Validation;

trait ValidatesWhenResolvedTrait
{
    /** @return void */
    public function validateResolved() {}
    /** @return void */
    protected function prepareForValidation() {}
    /** @return \Illuminate\Validation\Validator */
    protected function getValidatorInstance() {}
    /** @return void */
    protected function passedValidation() {}
    /** @return void */
    protected function failedValidation(\Illuminate\Validation\Validator $validator) {}
    /** @return bool */
    protected function passesAuthorization() {}
    /** @return void */
    protected function failedAuthorization() {}
}
