<?php

namespace Illuminate\Database;

abstract class Grammar
{
    use \Illuminate\Support\Traits\Macroable;
    protected $connection = NULL;
    public function __construct(\Illuminate\Database\Connection $connection) {}
    /** @return array<string> */
    public function wrapArray(array $values) {}
    /** @return string */
    public function wrapTable($table, $prefix = NULL) {}
    /** @return string */
    public function wrap($value) {}
    /** @return string */
    protected function wrapAliasedValue($value) {}
    /** @return string */
    protected function wrapAliasedTable($value, $prefix = NULL) {}
    /** @return string */
    protected function wrapSegments($segments) {}
    /** @return string */
    protected function wrapValue($value) {}
    /** @return string */
    protected function wrapJsonSelector($value) {}
    /** @return bool */
    protected function isJsonSelector($value) {}
    /** @return string */
    public function columnize(array $columns) {}
    /** @return string */
    public function parameterize(array $values) {}
    /** @return string */
    public function parameter($value) {}
    /** @return string */
    public function quoteString($value) {}
    /** @return string */
    public function escape($value, $binary = false) {}
    /** @return bool */
    public function isExpression($value) {}
    /** @return string|int|float */
    public function getValue($expression) {}
    /** @return string */
    public function getDateFormat() {}
    /** @return string */
    public function getTablePrefix() {}
    /** @return $this */
    public function setTablePrefix($prefix) {}
}
