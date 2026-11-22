<?php

namespace Illuminate\Database\Eloquent;

abstract class Model implements \Illuminate\Contracts\Support\Arrayable, \ArrayAccess, \Illuminate\Contracts\Support\CanBeEscapedWhenCastToString, \Illuminate\Contracts\Broadcasting\HasBroadcastChannel, \Illuminate\Contracts\Support\Jsonable, \JsonSerializable, \Illuminate\Contracts\Queue\QueueableEntity, \Stringable, \Illuminate\Contracts\Routing\UrlRoutable
{
    use \Illuminate\Database\Eloquent\Concerns\HasAttributes, \Illuminate\Database\Eloquent\Concerns\HasEvents, \Illuminate\Database\Eloquent\Concerns\HasGlobalScopes, \Illuminate\Database\Eloquent\Concerns\HasRelationships, \Illuminate\Database\Eloquent\Concerns\HasTimestamps, \Illuminate\Database\Eloquent\Concerns\HasUniqueIds, \Illuminate\Database\Eloquent\Concerns\HidesAttributes, \Illuminate\Database\Eloquent\Concerns\GuardsAttributes, \Illuminate\Database\Eloquent\Concerns\PreventsCircularRecursion, \Illuminate\Database\Eloquent\Concerns\TransformsToResource, \Illuminate\Support\Traits\ForwardsCalls, \Illuminate\Database\Eloquent\HasCollection;
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';
    protected $connection = NULL;
    protected $table = NULL;
    protected $primaryKey = 'id';
    protected $keyType = 'int';
    public $incrementing = true;
    protected $with = array (
);
    protected $withCount = array (
);
    public $preventsLazyLoading = false;
    protected $perPage = 15;
    public $exists = false;
    public $wasRecentlyCreated = false;
    protected $escapeWhenCastingToString = false;
    protected static $resolver = NULL;
    protected static $dispatcher = NULL;
    protected static $booted = array (
);
    protected static $bootedCallbacks = array (
);
    protected static $traitInitializers = array (
);
    protected static $globalScopes = array (
);
    protected static $ignoreOnTouch = array (
);
    protected static $modelsShouldPreventLazyLoading = false;
    protected static $modelsShouldAutomaticallyEagerLoadRelationships = false;
    protected static $lazyLoadingViolationCallback = NULL;
    protected static $modelsShouldPreventSilentlyDiscardingAttributes = false;
    protected static $discardedAttributeViolationCallback = NULL;
    protected static $modelsShouldPreventAccessingMissingAttributes = false;
    protected static $missingAttributeViolationCallback = NULL;
    protected static $isBroadcasting = true;
    protected static string $builder = 'Illuminate\\Database\\Eloquent\\Builder';
    protected static string $collectionClass = 'Illuminate\\Database\\Eloquent\\Collection';
    protected static array $isSoftDeletable;
    protected static array $isPrunable;
    protected static array $isMassPrunable;
    public function __construct(array $attributes = array (
)) {}
    protected function bootIfNotBooted() {}
    protected static function booting() {}
    protected static function boot() {}
    protected static function bootTraits() {}
    protected function initializeTraits() {}
    protected static function booted() {}
    protected static function whenBooted(\Closure $callback) {}
    public static function clearBootedModels() {}
    public static function withoutTouching(callable $callback) {}
    public static function withoutTouchingOn(array $models, callable $callback) {}
    public static function isIgnoringTouch($class = NULL) {}
    public static function shouldBeStrict(bool $shouldBeStrict = true) {}
    public static function preventLazyLoading($value = true) {}
    public static function automaticallyEagerLoadRelationships($value = true) {}
    public static function handleLazyLoadingViolationUsing(?callable $callback) {}
    public static function preventSilentlyDiscardingAttributes($value = true) {}
    public static function handleDiscardedAttributeViolationUsing(?callable $callback) {}
    public static function preventAccessingMissingAttributes($value = true) {}
    public static function handleMissingAttributeViolationUsing(?callable $callback) {}
    public static function withoutBroadcasting(callable $callback) {}
    public function fill(array $attributes) {}
    public function forceFill(array $attributes) {}
    public function qualifyColumn($column) {}
    public function qualifyColumns($columns) {}
    public function newInstance($attributes = array (
), $exists = false) {}
    public function newFromBuilder($attributes = array (
), $connection = NULL) {}
    public static function on($connection = NULL) {}
    public static function onWriteConnection() {}
    public static function all($columns = array (
  0 => '*',
)) {}
    public static function with($relations) {}
    public function load($relations) {}
    public function loadMorph($relation, $relations) {}
    public function loadMissing($relations) {}
    public function loadAggregate($relations, $column, $function = NULL) {}
    public function loadCount($relations) {}
    public function loadMax($relations, $column) {}
    public function loadMin($relations, $column) {}
    public function loadSum($relations, $column) {}
    public function loadAvg($relations, $column) {}
    public function loadExists($relations) {}
    public function loadMorphAggregate($relation, $relations, $column, $function = NULL) {}
    public function loadMorphCount($relation, $relations) {}
    public function loadMorphMax($relation, $relations, $column) {}
    public function loadMorphMin($relation, $relations, $column) {}
    public function loadMorphSum($relation, $relations, $column) {}
    public function loadMorphAvg($relation, $relations, $column) {}
    protected function increment($column, $amount = 1, array $extra = array (
)) {}
    protected function decrement($column, $amount = 1, array $extra = array (
)) {}
    protected function incrementOrDecrement($column, $amount, $extra, $method) {}
    public function update(array $attributes = array (
), array $options = array (
)) {}
    public function updateOrFail(array $attributes = array (
), array $options = array (
)) {}
    public function updateQuietly(array $attributes = array (
), array $options = array (
)) {}
    protected function incrementQuietly($column, $amount = 1, array $extra = array (
)) {}
    protected function decrementQuietly($column, $amount = 1, array $extra = array (
)) {}
    public function push() {}
    public function pushQuietly() {}
    public function saveQuietly(array $options = array (
)) {}
    public function save(array $options = array (
)) {}
    public function saveOrFail(array $options = array (
)) {}
    protected function finishSave(array $options) {}
    protected function performUpdate(\Illuminate\Database\Eloquent\Builder $query) {}
    protected function setKeysForSelectQuery($query) {}
    protected function getKeyForSelectQuery() {}
    protected function setKeysForSaveQuery($query) {}
    protected function getKeyForSaveQuery() {}
    protected function performInsert(\Illuminate\Database\Eloquent\Builder $query) {}
    protected function insertAndSetId(\Illuminate\Database\Eloquent\Builder $query, $attributes) {}
    public static function destroy($ids) {}
    public function delete() {}
    public function deleteQuietly() {}
    public function deleteOrFail() {}
    public function forceDelete() {}
    public static function forceDestroy($ids) {}
    protected function performDeleteOnModel() {}
    public static function query() {}
    public function newQuery() {}
    public function newModelQuery() {}
    public function newQueryWithoutRelationships() {}
    public function registerGlobalScopes($builder) {}
    public function newQueryWithoutScopes() {}
    public function newQueryWithoutScope($scope) {}
    public function newQueryForRestoration($ids) {}
    public function newEloquentBuilder($query) {}
    protected function resolveCustomBuilderClass() {}
    protected function newBaseQueryBuilder() {}
    public function newPivot(\Illuminate\Database\Eloquent\Model $parent, array $attributes, $table, $exists, $using = NULL) {}
    public function hasNamedScope($scope) {}
    public function callNamedScope($scope, array $parameters = array (
)) {}
    protected static function isScopeMethodWithAttribute(string $method) {}
    public function toArray() {}
    public function toJson($options = 0) {}
    public function toPrettyJson(int $options = 0) {}
    public function jsonSerialize(): mixed {}
    public function fresh($with = array (
)) {}
    public function refresh() {}
    public function replicate(?array $except = NULL) {}
    public function replicateQuietly(?array $except = NULL) {}
    public function is($model) {}
    public function isNot($model) {}
    public function getConnection() {}
    public function getConnectionName() {}
    public function setConnection($name) {}
    public static function resolveConnection($connection = NULL) {}
    public static function getConnectionResolver() {}
    public static function setConnectionResolver(\Illuminate\Database\ConnectionResolverInterface $resolver) {}
    public static function unsetConnectionResolver() {}
    public function getTable() {}
    public function setTable($table) {}
    public function getKeyName() {}
    public function setKeyName($key) {}
    public function getQualifiedKeyName() {}
    public function getKeyType() {}
    public function setKeyType($type) {}
    public function getIncrementing() {}
    public function setIncrementing($value) {}
    public function getKey() {}
    public function getQueueableId() {}
    public function getQueueableRelations() {}
    public function getQueueableConnection() {}
    public function getRouteKey() {}
    public function getRouteKeyName() {}
    public function resolveRouteBinding($value, $field = NULL) {}
    public function resolveSoftDeletableRouteBinding($value, $field = NULL) {}
    public function resolveChildRouteBinding($childType, $value, $field) {}
    public function resolveSoftDeletableChildRouteBinding($childType, $value, $field) {}
    protected function resolveChildRouteBindingQuery($childType, $value, $field) {}
    protected function childRouteBindingRelationshipName($childType) {}
    public function resolveRouteBindingQuery($query, $value, $field = NULL) {}
    public function getForeignKey() {}
    public function getPerPage() {}
    public function setPerPage($perPage) {}
    public static function isSoftDeletable(): bool {}
    protected function isPrunable(): bool {}
    protected function isMassPrunable(): bool {}
    public static function preventsLazyLoading() {}
    public static function isAutomaticallyEagerLoadingRelationships() {}
    public static function preventsSilentlyDiscardingAttributes() {}
    public static function preventsAccessingMissingAttributes() {}
    public function broadcastChannelRoute() {}
    public function broadcastChannel() {}
    public function __get($key) {}
    public function __set($key, $value) {}
    public function offsetExists($offset): bool {}
    public function offsetGet($offset): mixed {}
    public function offsetSet($offset, $value): void {}
    public function offsetUnset($offset): void {}
    public function __isset($key) {}
    public function __unset($key) {}
    public function __call($method, $parameters) {}
    public static function __callStatic($method, $parameters) {}
    public function __toString(): string {}
    public function escapeWhenCastingToString($escape = true) {}
    public function __sleep() {}
    public function __wakeup() {}
}
