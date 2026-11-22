<?php

namespace Illuminate\Database\Eloquent\Relations\Concerns;

trait InteractsWithPivotTable
{
    /** @return array */
    public function toggle($ids, $touch = true) {}
    /** @return array */
    public function toggleOrFail($ids, $touch = true) {}
    /** @return array{attached: */
    public function syncWithoutDetaching($ids) {}
    /** @return array{attached: */
    public function sync($ids, $detaching = true) {}
    /** @return array{attached: */
    public function syncOrFail($ids, $detaching = true) {}
    /** @return array{attached: */
    public function syncWithoutDetachingOrFail($ids) {}
    /** @return array{attached: */
    public function syncWithPivotValues($ids, array $values, bool $detaching = true) {}
    /** @return array{attached: */
    public function syncWithPivotValuesOrFail($ids, array $values, bool $detaching = true) {}
    /** @return array */
    protected function formatRecordsList(array $records) {}
    /** @return array */
    protected function attachNew(array $records, array $current, $touch = true) {}
    /** @return int */
    public function updateExistingPivot($id, array $attributes, $touch = true) {}
    /** @return int */
    public function updateExistingPivotOrFail($id, array $attributes, $touch = true) {}
    /** @return int */
    protected function updateExistingPivotUsingCustomClass($id, array $attributes, $touch) {}
    /** @return void */
    public function attach($ids, array $attributes = array (
), $touch = true) {}
    /** @return void */
    public function attachOrFail($ids, array $attributes = array (
), $touch = true) {}
    /** @return void */
    protected function attachUsingCustomClass($ids, array $attributes) {}
    /** @return array */
    protected function formatAttachRecords($ids, array $attributes) {}
    /** @return array */
    protected function formatAttachRecord($key, $value, $attributes, $hasTimestamps) {}
    /** @return array */
    protected function extractAttachIdAndAttributes($key, $value, array $attributes) {}
    /** @return array */
    protected function baseAttachRecord($id, $timed) {}
    /** @return array */
    protected function addTimestampsToAttachment(array $record, $exists = false) {}
    /** @return bool */
    public function hasPivotColumn($column) {}
    /** @return int */
    public function detach($ids = NULL, $touch = true) {}
    /** @return int */
    public function detachOrFail($ids = NULL, $touch = true) {}
    /** @return int */
    protected function detachUsingCustomClass($ids) {}
    /** @return \Illuminate\Support\Collection */
    protected function getCurrentlyAttachedPivots() {}
    /** @return \Illuminate\Support\Collection */
    protected function getCurrentlyAttachedPivotsForIds($ids = NULL) {}
    /** @return \Illuminate\Database\Eloquent\Relations\Pivot */
    public function newPivot(array $attributes = array (
), $exists = false) {}
    /** @return \Illuminate\Database\Eloquent\Relations\Pivot */
    public function newExistingPivot(array $attributes = array (
)) {}
    /** @return \Illuminate\Database\Query\Builder */
    public function newPivotStatement() {}
    /** @return \Illuminate\Database\Query\Builder */
    public function newPivotStatementForId($id) {}
    /** @return \Illuminate\Database\Query\Builder */
    public function newPivotQuery() {}
    /** @return $this */
    public function withPivot($columns) {}
    /** @return array */
    protected function parseIds($value) {}
    /** @return mixed */
    protected function parseId($value) {}
    /** @return array */
    protected function castKeys(array $keys) {}
    /** @return mixed */
    protected function castKey($key) {}
    /** @return array */
    protected function castAttributes($attributes) {}
    /** @return mixed */
    protected function getTypeSwapValue($type, $value) {}
}
