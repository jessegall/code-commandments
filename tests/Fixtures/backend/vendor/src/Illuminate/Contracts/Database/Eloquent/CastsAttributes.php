<?php

namespace Illuminate\Contracts\Database\Eloquent;

interface CastsAttributes
{
    public function get(\Illuminate\Database\Eloquent\Model $model, string $key, mixed $value, array $attributes);
    public function set(\Illuminate\Database\Eloquent\Model $model, string $key, mixed $value, array $attributes);
}
