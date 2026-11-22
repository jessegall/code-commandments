<?php

namespace Illuminate\Database\Eloquent\Concerns;

trait HasGlobalScopes
{
    /** @return void */
    public static function bootHasGlobalScopes() {}
    /** @return array */
    public static function resolveGlobalScopeAttributes() {}
    /** @return mixed */
    public static function addGlobalScope($scope, $implementation = NULL) {}
    /** @return void */
    public static function addGlobalScopes(array $scopes) {}
    /** @return bool */
    public static function hasGlobalScope($scope) {}
    /** @return \Illuminate\Database\Eloquent\Scope|(\Closure(\Illuminate\Database\Eloquent\Builder<static>): */
    public static function getGlobalScope($scope) {}
    /** @return array */
    public static function getAllGlobalScopes() {}
    /** @return void */
    public static function setAllGlobalScopes($scopes) {}
    /** @return array */
    public function getGlobalScopes() {}
}
