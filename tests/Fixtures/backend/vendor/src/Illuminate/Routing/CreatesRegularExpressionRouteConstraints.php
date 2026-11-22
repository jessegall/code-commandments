<?php

namespace Illuminate\Routing;

trait CreatesRegularExpressionRouteConstraints
{
    public function whereAlpha($parameters) {}
    public function whereAlphaNumeric($parameters) {}
    public function whereNumber($parameters) {}
    public function whereUlid($parameters) {}
    public function whereUuid($parameters) {}
    public function whereIn($parameters, array $values) {}
    protected function assignExpressionToParameters($parameters, $expression) {}
}
