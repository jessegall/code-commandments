<?php

namespace Illuminate\Routing;

trait CreatesRegularExpressionRouteConstraints
{
    /** @return $this */
    public function whereAlpha($parameters) {}
    /** @return $this */
    public function whereAlphaNumeric($parameters) {}
    /** @return $this */
    public function whereNumber($parameters) {}
    /** @return $this */
    public function whereUlid($parameters) {}
    /** @return $this */
    public function whereUuid($parameters) {}
    /** @return $this */
    public function whereIn($parameters, array $values) {}
    /** @return $this */
    protected function assignExpressionToParameters($parameters, $expression) {}
}
