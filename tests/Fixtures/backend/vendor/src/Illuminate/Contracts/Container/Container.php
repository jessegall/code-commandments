<?php

namespace Illuminate\Contracts\Container;

interface Container extends \Psr\Container\ContainerInterface
{
    public function get(string $id);
    public function bound($abstract);
    public function alias($abstract, $alias);
    public function tag($abstracts, $tags);
    public function tagged($tag);
    public function bind($abstract, $concrete = NULL, $shared = false);
    public function bindMethod($method, $callback);
    public function bindIf($abstract, $concrete = NULL, $shared = false);
    public function singleton($abstract, $concrete = NULL);
    public function singletonIf($abstract, $concrete = NULL);
    public function scoped($abstract, $concrete = NULL);
    public function scopedIf($abstract, $concrete = NULL);
    public function extend($abstract, \Closure $closure);
    public function instance($abstract, $instance);
    public function addContextualBinding($concrete, $abstract, $implementation);
    public function when($concrete);
    public function factory($abstract);
    public function flush();
    public function make($abstract, array $parameters = array (
));
    public function call($callback, array $parameters = array (
), $defaultMethod = NULL);
    public function resolved($abstract);
    public function beforeResolving($abstract, ?\Closure $callback = NULL);
    public function resolving($abstract, ?\Closure $callback = NULL);
    public function afterResolving($abstract, ?\Closure $callback = NULL);
}
