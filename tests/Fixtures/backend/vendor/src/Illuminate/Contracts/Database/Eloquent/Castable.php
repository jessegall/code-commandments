<?php

namespace Illuminate\Contracts\Database\Eloquent;

interface Castable
{
    /** @return class-string<CastsAttributes|CastsInboundAttributes>|CastsAttributes|CastsInboundAttributes */
    public static function castUsing(array $arguments);
}
