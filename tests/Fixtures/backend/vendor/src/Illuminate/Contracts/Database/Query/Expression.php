<?php

namespace Illuminate\Contracts\Database\Query;

interface Expression
{
    /** @return string|int|float */
    public function getValue(\Illuminate\Database\Grammar $grammar);
}
