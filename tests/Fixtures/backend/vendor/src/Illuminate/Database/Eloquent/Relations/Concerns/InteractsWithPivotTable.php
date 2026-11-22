<?php

namespace Illuminate\Database\Eloquent\Relations\Concerns;

trait InteractsWithPivotTable
{
    public function toggle($ids, $touch = true) {}
    public function toggleOrFail($ids, $touch = true) {}
    public function syncWithoutDetaching($ids) {}
    public function sync($ids, $detaching = true) {}
    public function syncOrFail($ids, $detaching = true) {}
    public function syncWithoutDetachingOrFail($ids) {}
    public function syncWithPivotValues($ids, array $values, bool $detaching = true) {}
    public function syncWithPivotValuesOrFail($ids, array $values, bool $detaching = true) {}
    protected function formatRecordsList(array $records) {}
    protected function attachNew(array $records, array $current, $touch = true) {}
    public function updateExistingPivot($id, array $attributes, $touch = true) {}
    public function updateExistingPivotOrFail($id, array $attributes, $touch = true) {}
    protected function updateExistingPivotUsingCustomClass($id, array $attributes, $touch) {}
    public function attach($ids, array $attributes = array (
), $touch = true) {}
    public function attachOrFail($ids, array $attributes = array (
), $touch = true) {}
    protected function attachUsingCustomClass($ids, array $attributes) {}
    protected function formatAttachRecords($ids, array $attributes) {}
    protected function formatAttachRecord($key, $value, $attributes, $hasTimestamps) {}
    protected function extractAttachIdAndAttributes($key, $value, array $attributes) {}
    protected function baseAttachRecord($id, $timed) {}
    protected function addTimestampsToAttachment(array $record, $exists = false) {}
    public function hasPivotColumn($column) {}
    public function detach($ids = NULL, $touch = true) {}
    public function detachOrFail($ids = NULL, $touch = true) {}
    protected function detachUsingCustomClass($ids) {}
    protected function getCurrentlyAttachedPivots() {}
    protected function getCurrentlyAttachedPivotsForIds($ids = NULL) {}
    public function newPivot(array $attributes = array (
), $exists = false) {}
    public function newExistingPivot(array $attributes = array (
)) {}
    public function newPivotStatement() {}
    public function newPivotStatementForId($id) {}
    public function newPivotQuery() {}
    public function withPivot($columns) {}
    protected function parseIds($value) {}
    protected function parseId($value) {}
    protected function castKeys(array $keys) {}
    protected function castKey($key) {}
    protected function castAttributes($attributes) {}
    protected function getTypeSwapValue($type, $value) {}
}
