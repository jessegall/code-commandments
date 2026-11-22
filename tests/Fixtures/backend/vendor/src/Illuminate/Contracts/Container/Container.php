<?php

namespace Illuminate\Contracts\Container;

interface Container extends \Psr\Container\ContainerInterface
{
    /** @return ($id */
    public function get(string $id);
    /** @return bool */
    public function bound($abstract);
    /** @return void */
    public function alias($abstract, $alias);
    /** @return void */
    public function tag($abstracts, $tags);
    /** @return iterable */
    public function tagged($tag);
    /** @return void */
    public function bind($abstract, $concrete = NULL, $shared = false);
    /** @return void */
    public function bindMethod($method, $callback);
    /** @return void */
    public function bindIf($abstract, $concrete = NULL, $shared = false);
    /** @return void */
    public function singleton($abstract, $concrete = NULL);
    /** @return void */
    public function singletonIf($abstract, $concrete = NULL);
    /** @return void */
    public function scoped($abstract, $concrete = NULL);
    /** @return void */
    public function scopedIf($abstract, $concrete = NULL);
    /** @return void */
    public function extend($abstract, \Closure $closure);
    /** @return TInstance */
    public function instance($abstract, $instance);
    /** @return void */
    public function addContextualBinding($concrete, $abstract, $implementation);
    /** @return \Illuminate\Contracts\Container\ContextualBindingBuilder */
    public function when($concrete);
    /** @return ($abstract */
    public function factory($abstract);
    /** @return void */
    public function flush();
    /** @return ($abstract */
    public function make($abstract, array $parameters = array (
));
    /** @return mixed */
    public function call($callback, array $parameters = array (
), $defaultMethod = NULL);
    /** @return bool */
    public function resolved($abstract);
    /** @return void */
    public function beforeResolving($abstract, ?\Closure $callback = NULL);
    /** @return void */
    public function resolving($abstract, ?\Closure $callback = NULL);
    /** @return void */
    public function afterResolving($abstract, ?\Closure $callback = NULL);
}
