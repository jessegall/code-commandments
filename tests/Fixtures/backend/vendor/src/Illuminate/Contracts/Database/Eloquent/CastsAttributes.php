<?php

namespace Illuminate\Contracts\Database\Eloquent;

interface CastsAttributes
{
    /** @return TGet|null */
    public function get(\Illuminate\Database\Eloquent\Model $model, string $key, mixed $value, array $attributes);
    /** @return mixed */
    public function set(\Illuminate\Database\Eloquent\Model $model, string $key, mixed $value, array $attributes);
}
