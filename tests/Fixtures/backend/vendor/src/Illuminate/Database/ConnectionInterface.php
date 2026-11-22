<?php

namespace Illuminate\Database;

interface ConnectionInterface
{
    /** @return \Illuminate\Database\Query\Builder */
    public function table($table, $as = NULL);
    /** @return \Illuminate\Contracts\Database\Query\Expression */
    public function raw($value);
    /** @return mixed */
    public function selectOne($query, $bindings = array (
), $useReadPdo = true);
    /** @return mixed */
    public function scalar($query, $bindings = array (
), $useReadPdo = true);
    /** @return array */
    public function select($query, $bindings = array (
), $useReadPdo = true);
    /** @return \Generator */
    public function cursor($query, $bindings = array (
), $useReadPdo = true);
    /** @return bool */
    public function insert($query, $bindings = array (
));
    /** @return int */
    public function update($query, $bindings = array (
));
    /** @return int */
    public function delete($query, $bindings = array (
));
    /** @return bool */
    public function statement($query, $bindings = array (
));
    /** @return int */
    public function affectingStatement($query, $bindings = array (
));
    /** @return bool */
    public function unprepared($query);
    /** @return array */
    public function prepareBindings(array $bindings);
    /** @return mixed */
    public function transaction(\Closure $callback, $attempts = 1);
    /** @return void */
    public function beginTransaction();
    /** @return void */
    public function commit();
    /** @return void */
    public function rollBack();
    /** @return int */
    public function transactionLevel();
    /** @return array */
    public function pretend(\Closure $callback);
    /** @return string */
    public function getDatabaseName();
}
