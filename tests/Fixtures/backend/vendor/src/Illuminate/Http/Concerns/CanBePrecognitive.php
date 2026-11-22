<?php

namespace Illuminate\Http\Concerns;

trait CanBePrecognitive
{
    public function filterPrecognitiveRules($rules) {}
    protected function shouldValidatePrecognitiveAttribute($attribute, $validateOnly) {}
    public function isAttemptingPrecognition() {}
    public function isPrecognitive() {}
}
