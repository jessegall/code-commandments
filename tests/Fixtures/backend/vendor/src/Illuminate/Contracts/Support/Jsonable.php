<?php

namespace Illuminate\Contracts\Support;

interface Jsonable
{
    /** @return string */
    public function toJson($options = 0);
}
