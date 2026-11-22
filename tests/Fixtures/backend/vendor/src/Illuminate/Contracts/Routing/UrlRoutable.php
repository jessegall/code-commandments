<?php

namespace Illuminate\Contracts\Routing;

interface UrlRoutable
{
    public function getRouteKey();
    public function getRouteKeyName();
    public function resolveRouteBinding($value, $field = NULL);
    public function resolveChildRouteBinding($childType, $value, $field);
}
