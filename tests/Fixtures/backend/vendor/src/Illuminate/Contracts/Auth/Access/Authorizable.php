<?php

namespace Illuminate\Contracts\Auth\Access;

interface Authorizable
{
    /** @return bool */
    public function can($abilities, $arguments = array (
));
}
