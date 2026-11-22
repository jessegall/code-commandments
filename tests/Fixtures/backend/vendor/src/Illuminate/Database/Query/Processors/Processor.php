<?php

namespace Illuminate\Database\Query\Processors;

class Processor
{
    /** @return array */
    public function processSelect(\Illuminate\Database\Query\Builder $query, $results) {}
    /** @return int */
    public function processInsertGetId(\Illuminate\Database\Query\Builder $query, $sql, $values, $sequence = NULL) {}
    /** @return list<array{name: */
    public function processSchemas($results) {}
    /** @return list<array{name: */
    public function processTables($results) {}
    /** @return list<array{name: */
    public function processViews($results) {}
    /** @return list<array{name: */
    public function processTypes($results) {}
    /** @return list<array{name: */
    public function processColumns($results) {}
    /** @return list<array{name: */
    public function processIndexes($results) {}
    /** @return list<array{name: */
    public function processForeignKeys($results) {}
}
