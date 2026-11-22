<?php

namespace Illuminate\Database;

interface ConnectionInterface
{
    public function table($table, $as = NULL);
    public function raw($value);
    public function selectOne($query, $bindings = array (
), $useReadPdo = true);
    public function scalar($query, $bindings = array (
), $useReadPdo = true);
    public function select($query, $bindings = array (
), $useReadPdo = true);
    public function cursor($query, $bindings = array (
), $useReadPdo = true);
    public function insert($query, $bindings = array (
));
    public function update($query, $bindings = array (
));
    public function delete($query, $bindings = array (
));
    public function statement($query, $bindings = array (
));
    public function affectingStatement($query, $bindings = array (
));
    public function unprepared($query);
    public function prepareBindings(array $bindings);
    public function transaction(\Closure $callback, $attempts = 1);
    public function beginTransaction();
    public function commit();
    public function rollBack();
    public function transactionLevel();
    public function pretend(\Closure $callback);
    public function getDatabaseName();
}
