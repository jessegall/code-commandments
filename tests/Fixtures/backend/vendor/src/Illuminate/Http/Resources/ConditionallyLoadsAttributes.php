<?php

namespace Illuminate\Http\Resources;

trait ConditionallyLoadsAttributes
{
    protected function filter($data) {}
    protected function mergeData($data, $index, $merge, $numericKeys) {}
    protected function removeMissingValues($data) {}
    protected function when($condition, $value, $default = null) {}
    public function unless($condition, $value, $default = null) {}
    protected function merge($value) {}
    protected function mergeWhen($condition, $value, $default = null) {}
    protected function mergeUnless($condition, $value, $default = null) {}
    protected function attributes($attributes) {}
    public function whenHas($attribute, $value = NULL, $default = null) {}
    protected function whenNull($value, $default = null) {}
    protected function whenNotNull($value, $default = null) {}
    protected function whenAppended($attribute, $value = NULL, $default = null) {}
    protected function whenLoaded($relationship, $value = NULL, $default = null) {}
    public function whenCounted($relationship, $value = NULL, $default = null) {}
    public function whenAggregated($relationship, $column, $aggregate, $value = NULL, $default = null) {}
    public function whenExistsLoaded($relationship, $value = NULL, $default = null) {}
    protected function whenPivotLoaded($table, $value, $default = null) {}
    protected function whenPivotLoadedAs($accessor, $table, $value, $default = null) {}
    protected function hasPivotLoaded($table) {}
    protected function hasPivotLoadedAs($accessor, $table) {}
    protected function transform($value, callable $callback, $default = null) {}
}
