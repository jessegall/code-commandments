<?php

namespace Illuminate\Contracts\Database\Eloquent;

interface CastsInboundAttributes
{
    public function set(\Illuminate\Database\Eloquent\Model $model, string $key, mixed $value, array $attributes);
}
