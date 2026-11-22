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
    /** @return void */
    protected function bootIfNotBooted() {}
    /** @return void */
    protected static function booting() {}
    /** @return void */
    protected static function boot() {}
    /** @return void */
    protected static function bootTraits() {}
    /** @return void */
    protected function initializeTraits() {}
    /** @return void */
    protected static function booted() {}
    /** @return void */
    protected static function whenBooted(\Closure $callback) {}
    /** @return void */
    public static function clearBootedModels() {}
    /** @return void */
    public static function withoutTouching(callable $callback) {}
    /** @return void */
    public static function withoutTouchingOn(array $models, callable $callback) {}
    /** @return bool */
    public static function isIgnoringTouch($class = NULL) {}
    /** @return void */
    public static function shouldBeStrict(bool $shouldBeStrict = true) {}
    /** @return void */
    public static function preventLazyLoading($value = true) {}
    /** @return void */
    public static function automaticallyEagerLoadRelationships($value = true) {}
    /** @return void */
    public static function handleLazyLoadingViolationUsing(?callable $callback) {}
    /** @return void */
    public static function preventSilentlyDiscardingAttributes($value = true) {}
    /** @return void */
    public static function handleDiscardedAttributeViolationUsing(?callable $callback) {}
    /** @return void */
    public static function preventAccessingMissingAttributes($value = true) {}
    /** @return void */
    public static function handleMissingAttributeViolationUsing(?callable $callback) {}
    /** @return mixed */
    public static function withoutBroadcasting(callable $callback) {}
    /** @return $this */
    public function fill(array $attributes) {}
    /** @return $this */
    public function forceFill(array $attributes) {}
    /** @return string */
    public function qualifyColumn($column) {}
    /** @return array */
    public function qualifyColumns($columns) {}
    /** @return static */
    public function newInstance($attributes = array (
), $exists = false) {}
    /** @return static */
    public function newFromBuilder($attributes = array (
), $connection = NULL) {}
    /** @return \Illuminate\Database\Eloquent\Builder<static> */
    public static function on($connection = NULL) {}
    /** @return \Illuminate\Database\Eloquent\Builder<static> */
    public static function onWriteConnection() {}
    /** @return \Illuminate\Database\Eloquent\Collection<int, */
    public static function all($columns = array (
  0 => '*',
)) {}
    /** @return \Illuminate\Database\Eloquent\Builder<static> */
    public static function with($relations) {}
    /** @return $this */
    public function load($relations) {}
    /** @return $this */
    public function loadMorph($relation, $relations) {}
    /** @return $this */
    public function loadMissing($relations) {}
    /** @return $this */
    public function loadAggregate($relations, $column, $function = NULL) {}
    /** @return $this */
    public function loadCount($relations) {}
    /** @return $this */
    public function loadMax($relations, $column) {}
    /** @return $this */
    public function loadMin($relations, $column) {}
    /** @return $this */
    public function loadSum($relations, $column) {}
    /** @return $this */
    public function loadAvg($relations, $column) {}
    /** @return $this */
    public function loadExists($relations) {}
    /** @return $this */
    public function loadMorphAggregate($relation, $relations, $column, $function = NULL) {}
    /** @return $this */
    public function loadMorphCount($relation, $relations) {}
    /** @return $this */
    public function loadMorphMax($relation, $relations, $column) {}
    /** @return $this */
    public function loadMorphMin($relation, $relations, $column) {}
    /** @return $this */
    public function loadMorphSum($relation, $relations, $column) {}
    /** @return $this */
    public function loadMorphAvg($relation, $relations, $column) {}
    /** @return int */
    protected function increment($column, $amount = 1, array $extra = array (
)) {}
    /** @return int */
    protected function decrement($column, $amount = 1, array $extra = array (
)) {}
    /** @return int */
    protected function incrementOrDecrement($column, $amount, $extra, $method) {}
    /** @return bool */
    public function update(array $attributes = array (
), array $options = array (
)) {}
    /** @return bool */
    public function updateOrFail(array $attributes = array (
), array $options = array (
)) {}
    /** @return bool */
    public function updateQuietly(array $attributes = array (
), array $options = array (
)) {}
    /** @return int */
    protected function incrementQuietly($column, $amount = 1, array $extra = array (
)) {}
    /** @return int */
    protected function decrementQuietly($column, $amount = 1, array $extra = array (
)) {}
    /** @return bool */
    public function push() {}
    /** @return bool */
    public function pushQuietly() {}
    /** @return bool */
    public function saveQuietly(array $options = array (
)) {}
    /** @return bool */
    public function save(array $options = array (
)) {}
    /** @return bool */
    public function saveOrFail(array $options = array (
)) {}
    /** @return void */
    protected function finishSave(array $options) {}
    /** @return bool */
    protected function performUpdate(\Illuminate\Database\Eloquent\Builder $query) {}
    /** @return \Illuminate\Database\Eloquent\Builder<static> */
    protected function setKeysForSelectQuery($query) {}
    /** @return mixed */
    protected function getKeyForSelectQuery() {}
    /** @return \Illuminate\Database\Eloquent\Builder<static> */
    protected function setKeysForSaveQuery($query) {}
    /** @return mixed */
    protected function getKeyForSaveQuery() {}
    /** @return bool */
    protected function performInsert(\Illuminate\Database\Eloquent\Builder $query) {}
    /** @return void */
    protected function insertAndSetId(\Illuminate\Database\Eloquent\Builder $query, $attributes) {}
    /** @return int */
    public static function destroy($ids) {}
    /** @return bool|null */
    public function delete() {}
    /** @return bool */
    public function deleteQuietly() {}
    /** @return bool|null */
    public function deleteOrFail() {}
    /** @return bool|null */
    public function forceDelete() {}
    /** @return bool|null */
    public static function forceDestroy($ids) {}
    /** @return void */
    protected function performDeleteOnModel() {}
    /** @return \Illuminate\Database\Eloquent\Builder<static> */
    public static function query() {}
    /** @return \Illuminate\Database\Eloquent\Builder<static> */
    public function newQuery() {}
    /** @return \Illuminate\Database\Eloquent\Builder<static> */
    public function newModelQuery() {}
    /** @return \Illuminate\Database\Eloquent\Builder<static> */
    public function newQueryWithoutRelationships() {}
    /** @return \Illuminate\Database\Eloquent\Builder<static> */
    public function registerGlobalScopes($builder) {}
    /** @return \Illuminate\Database\Eloquent\Builder<static> */
    public function newQueryWithoutScopes() {}
    /** @return \Illuminate\Database\Eloquent\Builder<static> */
    public function newQueryWithoutScope($scope) {}
    /** @return \Illuminate\Database\Eloquent\Builder<static> */
    public function newQueryForRestoration($ids) {}
    /** @return \Illuminate\Database\Eloquent\Builder<*> */
    public function newEloquentBuilder($query) {}
    /** @return class-string<\Illuminate\Database\Eloquent\Builder>|false */
    protected function resolveCustomBuilderClass() {}
    /** @return \Illuminate\Database\Query\Builder */
    protected function newBaseQueryBuilder() {}
    /** @return \Illuminate\Database\Eloquent\Relations\Pivot */
    public function newPivot(\Illuminate\Database\Eloquent\Model $parent, array $attributes, $table, $exists, $using = NULL) {}
    /** @return bool */
    public function hasNamedScope($scope) {}
    /** @return mixed */
    public function callNamedScope($scope, array $parameters = array (
)) {}
    /** @return bool */
    protected static function isScopeMethodWithAttribute(string $method) {}
    /** @return array */
    public function toArray() {}
    /** @return string */
    public function toJson($options = 0) {}
    /** @return string */
    public function toPrettyJson(int $options = 0) {}
    public function jsonSerialize(): mixed {}
    /** @return static|null */
    public function fresh($with = array (
)) {}
    /** @return $this */
    public function refresh() {}
    /** @return static */
    public function replicate(?array $except = NULL) {}
    /** @return static */
    public function replicateQuietly(?array $except = NULL) {}
    /** @return bool */
    public function is($model) {}
    /** @return bool */
    public function isNot($model) {}
    /** @return \Illuminate\Database\Connection */
    public function getConnection() {}
    /** @return string|null */
    public function getConnectionName() {}
    /** @return $this */
    public function setConnection($name) {}
    /** @return \Illuminate\Database\Connection */
    public static function resolveConnection($connection = NULL) {}
    /** @return \Illuminate\Database\ConnectionResolverInterface|null */
    public static function getConnectionResolver() {}
    /** @return void */
    public static function setConnectionResolver(\Illuminate\Database\ConnectionResolverInterface $resolver) {}
    /** @return void */
    public static function unsetConnectionResolver() {}
    /** @return string */
    public function getTable() {}
    /** @return $this */
    public function setTable($table) {}
    /** @return string */
    public function getKeyName() {}
    /** @return $this */
    public function setKeyName($key) {}
    /** @return string */
    public function getQualifiedKeyName() {}
    /** @return string */
    public function getKeyType() {}
    /** @return $this */
    public function setKeyType($type) {}
    /** @return bool */
    public function getIncrementing() {}
    /** @return $this */
    public function setIncrementing($value) {}
    /** @return mixed */
    public function getKey() {}
    /** @return mixed */
    public function getQueueableId() {}
    /** @return array */
    public function getQueueableRelations() {}
    /** @return string|null */
    public function getQueueableConnection() {}
    /** @return mixed */
    public function getRouteKey() {}
    /** @return string */
    public function getRouteKeyName() {}
    /** @return \Illuminate\Database\Eloquent\Model|null */
    public function resolveRouteBinding($value, $field = NULL) {}
    /** @return \Illuminate\Database\Eloquent\Model|null */
    public function resolveSoftDeletableRouteBinding($value, $field = NULL) {}
    /** @return \Illuminate\Database\Eloquent\Model|null */
    public function resolveChildRouteBinding($childType, $value, $field) {}
    /** @return \Illuminate\Database\Eloquent\Model|null */
    public function resolveSoftDeletableChildRouteBinding($childType, $value, $field) {}
    /** @return \Illuminate\Database\Eloquent\Relations\Relation<\Illuminate\Database\Eloquent\Model, */
    protected function resolveChildRouteBindingQuery($childType, $value, $field) {}
    /** @return string */
    protected function childRouteBindingRelationshipName($childType) {}
    /** @return \Illuminate\Contracts\Database\Eloquent\Builder */
    public function resolveRouteBindingQuery($query, $value, $field = NULL) {}
    /** @return string */
    public function getForeignKey() {}
    /** @return int */
    public function getPerPage() {}
    /** @return $this */
    public function setPerPage($perPage) {}
    public static function isSoftDeletable(): bool {}
    protected function isPrunable(): bool {}
    protected function isMassPrunable(): bool {}
    /** @return bool */
    public static function preventsLazyLoading() {}
    /** @return bool */
    public static function isAutomaticallyEagerLoadingRelationships() {}
    /** @return bool */
    public static function preventsSilentlyDiscardingAttributes() {}
    /** @return bool */
    public static function preventsAccessingMissingAttributes() {}
    /** @return string */
    public function broadcastChannelRoute() {}
    /** @return string */
    public function broadcastChannel() {}
    /** @return mixed */
    public function __get($key) {}
    /** @return void */
    public function __set($key, $value) {}
    public function offsetExists($offset): bool {}
    public function offsetGet($offset): mixed {}
    public function offsetSet($offset, $value): void {}
    public function offsetUnset($offset): void {}
    /** @return bool */
    public function __isset($key) {}
    /** @return void */
    public function __unset($key) {}
    /** @return mixed */
    public function __call($method, $parameters) {}
    /** @return mixed */
    public static function __callStatic($method, $parameters) {}
    public function __toString(): string {}
    /** @return $this */
    public function escapeWhenCastingToString($escape = true) {}
    /** @return array */
    public function __sleep() {}
    /** @return void */
    public function __wakeup() {}
}
