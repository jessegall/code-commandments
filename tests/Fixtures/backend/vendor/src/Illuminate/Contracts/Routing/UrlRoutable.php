<?php

namespace Illuminate\Contracts\Routing;

interface UrlRoutable
{
    /** @return mixed */
    public function getRouteKey();
    /** @return string */
    public function getRouteKeyName();
    /** @return \Illuminate\Database\Eloquent\Model|null */
    public function resolveRouteBinding($value, $field = NULL);
    /** @return \Illuminate\Database\Eloquent\Model|null */
    public function resolveChildRouteBinding($childType, $value, $field);
}
