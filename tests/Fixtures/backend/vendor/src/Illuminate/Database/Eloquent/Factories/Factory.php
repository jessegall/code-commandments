<?php

namespace Illuminate\Database\Eloquent\Factories;

abstract class Factory
{
    use \Illuminate\Support\Traits\Conditionable, \Illuminate\Support\Traits\ForwardsCalls, \Illuminate\Support\Traits\Macroable;
    protected $model = NULL;
    protected $count = NULL;
    protected $states = NULL;
    protected $has = NULL;
    protected $for = NULL;
    protected $recycle = NULL;
    protected $afterMaking = NULL;
    protected $afterCreating = NULL;
    protected $expandRelationships = true;
    protected $excludeRelationships = array (
);
    protected $connection = NULL;
    protected $faker = NULL;
    public static $namespace = 'Database\\Factories\\';
    protected static $modelNameResolver = NULL;
    protected static $modelNameResolvers = array (
);
    protected static $factoryNameResolver = NULL;
    protected static $expandRelationshipsByDefault = true;
    public function __construct($count = NULL, ?\Illuminate\Support\Collection $states = NULL, ?\Illuminate\Support\Collection $has = NULL, ?\Illuminate\Support\Collection $for = NULL, ?\Illuminate\Support\Collection $afterMaking = NULL, ?\Illuminate\Support\Collection $afterCreating = NULL, $connection = NULL, ?\Illuminate\Support\Collection $recycle = NULL, ?bool $expandRelationships = NULL, array $excludeRelationships = array (
)) {}
    abstract public function definition();
    public static function new($attributes = array (
)) {}
    public static function times(int $count) {}
    public function configure() {}
    public function raw($attributes = array (
), ?\Illuminate\Database\Eloquent\Model $parent = NULL) {}
    public function createOne($attributes = array (
)) {}
    public function createOneQuietly($attributes = array (
)) {}
    public function createMany(\Traversable|array|int|null $records = NULL) {}
    public function createManyQuietly(\Traversable|array|int|null $records = NULL) {}
    public function create($attributes = array (
), ?\Illuminate\Database\Eloquent\Model $parent = NULL) {}
    public function createQuietly($attributes = array (
), ?\Illuminate\Database\Eloquent\Model $parent = NULL) {}
    public function lazy(array $attributes = array (
), ?\Illuminate\Database\Eloquent\Model $parent = NULL) {}
    protected function store(\Illuminate\Support\Collection $results) {}
    protected function createChildren(\Illuminate\Database\Eloquent\Model $model) {}
    public function makeOne($attributes = array (
)) {}
    public function make($attributes = array (
), ?\Illuminate\Database\Eloquent\Model $parent = NULL) {}
    public function makeMany(\Traversable|array|int|null $records = NULL) {}
    public function insert(array $attributes = array (
), ?\Illuminate\Database\Eloquent\Model $parent = NULL): void {}
    protected function makeInstance(?\Illuminate\Database\Eloquent\Model $parent) {}
    protected function getExpandedAttributes(?\Illuminate\Database\Eloquent\Model $parent) {}
    protected function getRawAttributes(?\Illuminate\Database\Eloquent\Model $parent) {}
    protected function parentResolvers() {}
    protected function expandAttributes(array $definition) {}
    public function state($state) {}
    public function prependState($state) {}
    public function set($key, $value) {}
    public function sequence(...$sequence) {}
    public function forEachSequence(...$sequence) {}
    public function crossJoinSequence(...$sequence) {}
    public function has(\Illuminate\Database\Eloquent\Factories\Factory $factory, $relationship = NULL) {}
    protected function guessRelationship(string $related) {}
    public function hasAttached($factory, $pivot = array (
), $relationship = NULL) {}
    public function for($factory, $relationship = NULL) {}
    public function recycle($model) {}
    public function getRandomRecycledModel($modelClassName) {}
    public function afterMaking(\Closure $callback) {}
    public function afterCreating(\Closure $callback) {}
    public function withoutAfterMaking() {}
    public function withoutAfterCreating() {}
    protected function callAfterMaking(\Illuminate\Support\Collection $instances) {}
    protected function callAfterCreating(\Illuminate\Support\Collection $instances, ?\Illuminate\Database\Eloquent\Model $parent = NULL) {}
    public function count(?int $count) {}
    public function withoutParents($parents = array (
)) {}
    public function getConnectionName() {}
    public function connection(\UnitEnum|string|null $connection) {}
    protected function newInstance(array $arguments = array (
)) {}
    public function newModel(array $attributes = array (
)) {}
    public function modelName() {}
    public static function guessModelNamesUsing(callable $callback) {}
    public static function useNamespace(string $namespace) {}
    public static function factoryForModel(string $modelName) {}
    public static function guessFactoryNamesUsing(callable $callback) {}
    public static function expandRelationshipsByDefault() {}
    public static function dontExpandRelationshipsByDefault() {}
    protected function withFaker() {}
    public static function resolveFactoryName(string $modelName) {}
    protected static function appNamespace() {}
    public static function flushState() {}
    public function __call($method, $parameters) {}
    public function macroCall($method, $parameters) {}
}
