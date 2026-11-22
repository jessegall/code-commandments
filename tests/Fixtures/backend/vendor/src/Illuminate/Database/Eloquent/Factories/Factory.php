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
    /** @return array<string, */
    abstract public function definition();
    /** @return static */
    public static function new($attributes = array (
)) {}
    /** @return static */
    public static function times(int $count) {}
    /** @return static */
    public function configure() {}
    /** @return array<int|string, */
    public function raw($attributes = array (
), ?\Illuminate\Database\Eloquent\Model $parent = NULL) {}
    /** @return TModel */
    public function createOne($attributes = array (
)) {}
    /** @return TModel */
    public function createOneQuietly($attributes = array (
)) {}
    /** @return \Illuminate\Database\Eloquent\Collection<int, */
    public function createMany(\Traversable|array|int|null $records = NULL) {}
    /** @return \Illuminate\Database\Eloquent\Collection<int, */
    public function createManyQuietly(\Traversable|array|int|null $records = NULL) {}
    /** @return \Illuminate\Database\Eloquent\Collection<int, */
    public function create($attributes = array (
), ?\Illuminate\Database\Eloquent\Model $parent = NULL) {}
    /** @return \Illuminate\Database\Eloquent\Collection<int, */
    public function createQuietly($attributes = array (
), ?\Illuminate\Database\Eloquent\Model $parent = NULL) {}
    /** @return \Closure(): */
    public function lazy(array $attributes = array (
), ?\Illuminate\Database\Eloquent\Model $parent = NULL) {}
    /** @return void */
    protected function store(\Illuminate\Support\Collection $results) {}
    /** @return void */
    protected function createChildren(\Illuminate\Database\Eloquent\Model $model) {}
    /** @return TModel */
    public function makeOne($attributes = array (
)) {}
    /** @return \Illuminate\Database\Eloquent\Collection<int, */
    public function make($attributes = array (
), ?\Illuminate\Database\Eloquent\Model $parent = NULL) {}
    /** @return \Illuminate\Database\Eloquent\Collection<int, */
    public function makeMany(\Traversable|array|int|null $records = NULL) {}
    public function insert(array $attributes = array (
), ?\Illuminate\Database\Eloquent\Model $parent = NULL): void {}
    /** @return \Illuminate\Database\Eloquent\Model */
    protected function makeInstance(?\Illuminate\Database\Eloquent\Model $parent) {}
    /** @return mixed */
    protected function getExpandedAttributes(?\Illuminate\Database\Eloquent\Model $parent) {}
    /** @return array */
    protected function getRawAttributes(?\Illuminate\Database\Eloquent\Model $parent) {}
    /** @return array */
    protected function parentResolvers() {}
    /** @return array */
    protected function expandAttributes(array $definition) {}
    /** @return static */
    public function state($state) {}
    /** @return static */
    public function prependState($state) {}
    /** @return static */
    public function set($key, $value) {}
    /** @return static */
    public function sequence(...$sequence) {}
    /** @return static */
    public function forEachSequence(...$sequence) {}
    /** @return static */
    public function crossJoinSequence(...$sequence) {}
    /** @return static */
    public function has(\Illuminate\Database\Eloquent\Factories\Factory $factory, $relationship = NULL) {}
    /** @return string */
    protected function guessRelationship(string $related) {}
    /** @return static */
    public function hasAttached($factory, $pivot = array (
), $relationship = NULL) {}
    /** @return static */
    public function for($factory, $relationship = NULL) {}
    /** @return static */
    public function recycle($model) {}
    /** @return TClass|null */
    public function getRandomRecycledModel($modelClassName) {}
    /** @return static */
    public function afterMaking(\Closure $callback) {}
    /** @return static */
    public function afterCreating(\Closure $callback) {}
    /** @return static */
    public function withoutAfterMaking() {}
    /** @return static */
    public function withoutAfterCreating() {}
    /** @return void */
    protected function callAfterMaking(\Illuminate\Support\Collection $instances) {}
    /** @return void */
    protected function callAfterCreating(\Illuminate\Support\Collection $instances, ?\Illuminate\Database\Eloquent\Model $parent = NULL) {}
    /** @return static */
    public function count(?int $count) {}
    /** @return static */
    public function withoutParents($parents = array (
)) {}
    /** @return string */
    public function getConnectionName() {}
    /** @return static */
    public function connection(\UnitEnum|string|null $connection) {}
    /** @return static */
    protected function newInstance(array $arguments = array (
)) {}
    /** @return TModel */
    public function newModel(array $attributes = array (
)) {}
    /** @return class-string<TModel> */
    public function modelName() {}
    /** @return void */
    public static function guessModelNamesUsing(callable $callback) {}
    /** @return void */
    public static function useNamespace(string $namespace) {}
    /** @return \Illuminate\Database\Eloquent\Factories\Factory<TClass> */
    public static function factoryForModel(string $modelName) {}
    /** @return void */
    public static function guessFactoryNamesUsing(callable $callback) {}
    /** @return void */
    public static function expandRelationshipsByDefault() {}
    /** @return void */
    public static function dontExpandRelationshipsByDefault() {}
    /** @return \Faker\Generator|null */
    protected function withFaker() {}
    /** @return class-string<\Illuminate\Database\Eloquent\Factories\Factory<TClass>> */
    public static function resolveFactoryName(string $modelName) {}
    /** @return string */
    protected static function appNamespace() {}
    /** @return void */
    public static function flushState() {}
    /** @return mixed */
    public function __call($method, $parameters) {}
    /** @return mixed */
    public function macroCall($method, $parameters) {}
}
