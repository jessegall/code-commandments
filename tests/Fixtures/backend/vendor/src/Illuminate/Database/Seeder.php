<?php

namespace Illuminate\Database;

abstract class Seeder
{
    protected $container = NULL;
    protected $command = NULL;
    protected static $called = array (
);
    /** @return $this */
    public function call($class, $silent = false, array $parameters = array (
)) {}
    /** @return void */
    public function callWith($class, array $parameters = array (
)) {}
    /** @return void */
    public function callSilent($class, array $parameters = array (
)) {}
    /** @return void */
    public function callOnce($class, $silent = false, array $parameters = array (
)) {}
    /** @return \Illuminate\Database\Seeder */
    protected function resolve($class) {}
    /** @return $this */
    public function setContainer(\Illuminate\Contracts\Container\Container $container) {}
    /** @return $this */
    public function setCommand(\Illuminate\Console\Command $command) {}
    /** @return mixed */
    public function __invoke(array $parameters = array (
)) {}
}
