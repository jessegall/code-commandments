<?php

namespace Illuminate\Database;

abstract class Grammar
{
    use \Illuminate\Support\Traits\Macroable;
    protected $connection = NULL;
    public function __construct(\Illuminate\Database\Connection $connection) {}
    public function wrapArray(array $values) {}
    public function wrapTable($table, $prefix = NULL) {}
    public function wrap($value) {}
    protected function wrapAliasedValue($value) {}
    protected function wrapAliasedTable($value, $prefix = NULL) {}
    protected function wrapSegments($segments) {}
    protected function wrapValue($value) {}
    protected function wrapJsonSelector($value) {}
    protected function isJsonSelector($value) {}
    public function columnize(array $columns) {}
    public function parameterize(array $values) {}
    public function parameter($value) {}
    public function quoteString($value) {}
    public function escape($value, $binary = false) {}
    public function isExpression($value) {}
    public function getValue($expression) {}
    public function getDateFormat() {}
    public function getTablePrefix() {}
    public function setTablePrefix($prefix) {}
}
