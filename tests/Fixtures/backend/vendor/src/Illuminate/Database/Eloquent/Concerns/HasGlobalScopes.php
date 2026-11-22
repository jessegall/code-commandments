<?php

namespace Illuminate\Database\Eloquent\Concerns;

trait HasGlobalScopes
{
    public static function bootHasGlobalScopes() {}
    public static function resolveGlobalScopeAttributes() {}
    public static function addGlobalScope($scope, $implementation = NULL) {}
    public static function addGlobalScopes(array $scopes) {}
    public static function hasGlobalScope($scope) {}
    public static function getGlobalScope($scope) {}
    public static function getAllGlobalScopes() {}
    public static function setAllGlobalScopes($scopes) {}
    public function getGlobalScopes() {}
}
