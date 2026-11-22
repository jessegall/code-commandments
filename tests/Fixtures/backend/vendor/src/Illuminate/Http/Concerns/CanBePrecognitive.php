<?php

namespace Illuminate\Http\Concerns;

trait CanBePrecognitive
{
    /** @return array */
    public function filterPrecognitiveRules($rules) {}
    /** @return bool */
    protected function shouldValidatePrecognitiveAttribute($attribute, $validateOnly) {}
    /** @return bool */
    public function isAttemptingPrecognition() {}
    /** @return bool */
    public function isPrecognitive() {}
}
