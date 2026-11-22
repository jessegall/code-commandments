<?php

namespace Illuminate\Http\Resources;

trait ConditionallyLoadsAttributes
{
    /** @return array */
    protected function filter($data) {}
    /** @return array */
    protected function mergeData($data, $index, $merge, $numericKeys) {}
    /** @return array */
    protected function removeMissingValues($data) {}
    /** @return \Illuminate\Http\Resources\MissingValue|mixed */
    protected function when($condition, $value, $default = null) {}
    /** @return \Illuminate\Http\Resources\MissingValue|mixed */
    public function unless($condition, $value, $default = null) {}
    /** @return \Illuminate\Http\Resources\MergeValue|mixed */
    protected function merge($value) {}
    /** @return \Illuminate\Http\Resources\MergeValue|mixed */
    protected function mergeWhen($condition, $value, $default = null) {}
    /** @return \Illuminate\Http\Resources\MergeValue|mixed */
    protected function mergeUnless($condition, $value, $default = null) {}
    /** @return \Illuminate\Http\Resources\MergeValue */
    protected function attributes($attributes) {}
    /** @return \Illuminate\Http\Resources\MissingValue|mixed */
    public function whenHas($attribute, $value = NULL, $default = null) {}
    /** @return \Illuminate\Http\Resources\MissingValue|mixed */
    protected function whenNull($value, $default = null) {}
    /** @return \Illuminate\Http\Resources\MissingValue|mixed */
    protected function whenNotNull($value, $default = null) {}
    /** @return \Illuminate\Http\Resources\MissingValue|mixed */
    protected function whenAppended($attribute, $value = NULL, $default = null) {}
    /** @return \Illuminate\Http\Resources\MissingValue|mixed */
    protected function whenLoaded($relationship, $value = NULL, $default = null) {}
    /** @return \Illuminate\Http\Resources\MissingValue|mixed */
    public function whenCounted($relationship, $value = NULL, $default = null) {}
    /** @return \Illuminate\Http\Resources\MissingValue|mixed */
    public function whenAggregated($relationship, $column, $aggregate, $value = NULL, $default = null) {}
    /** @return \Illuminate\Http\Resources\MissingValue|mixed */
    public function whenExistsLoaded($relationship, $value = NULL, $default = null) {}
    /** @return \Illuminate\Http\Resources\MissingValue|mixed */
    protected function whenPivotLoaded($table, $value, $default = null) {}
    /** @return \Illuminate\Http\Resources\MissingValue|mixed */
    protected function whenPivotLoadedAs($accessor, $table, $value, $default = null) {}
    /** @return bool */
    protected function hasPivotLoaded($table) {}
    /** @return bool */
    protected function hasPivotLoadedAs($accessor, $table) {}
    /** @return mixed */
    protected function transform($value, callable $callback, $default = null) {}
}
