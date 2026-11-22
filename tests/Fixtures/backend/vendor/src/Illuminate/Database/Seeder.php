<?php

namespace Illuminate\Database;

abstract class Seeder
{
    protected $container = NULL;
    protected $command = NULL;
    protected static $called = array (
);
    public function call($class, $silent = false, array $parameters = array (
)) {}
    public function callWith($class, array $parameters = array (
)) {}
    public function callSilent($class, array $parameters = array (
)) {}
    public function callOnce($class, $silent = false, array $parameters = array (
)) {}
    protected function resolve($class) {}
    public function setContainer(\Illuminate\Contracts\Container\Container $container) {}
    public function setCommand(\Illuminate\Console\Command $command) {}
    public function __invoke(array $parameters = array (
)) {}
}
