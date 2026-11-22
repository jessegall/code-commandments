<?php

namespace Illuminate\Contracts\Validation;

interface ValidatesWhenResolved
{
    /** @return void */
    public function validateResolved();
}
