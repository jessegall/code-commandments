<?php

namespace Illuminate\Database\Query\Processors;

class Processor
{
    public function processSelect(\Illuminate\Database\Query\Builder $query, $results) {}
    public function processInsertGetId(\Illuminate\Database\Query\Builder $query, $sql, $values, $sequence = NULL) {}
    public function processSchemas($results) {}
    public function processTables($results) {}
    public function processViews($results) {}
    public function processTypes($results) {}
    public function processColumns($results) {}
    public function processIndexes($results) {}
    public function processForeignKeys($results) {}
}
