<?php

namespace Illuminate\Database\Eloquent\Concerns;

trait HasAttributes
{
    protected $attributes = array (
);
    protected $original = array (
);
    protected $changes = array (
);
    protected $previous = array (
);
    protected $casts = array (
);
    protected $classCastCache = array (
);
    protected $attributeCastCache = array (
);
    protected static $primitiveCastTypes = array (
  0 => 'array',
  1 => 'bool',
  2 => 'boolean',
  3 => 'collection',
  4 => 'custom_datetime',
  5 => 'date',
  6 => 'datetime',
  7 => 'decimal',
  8 => 'double',
  9 => 'encrypted',
  10 => 'encrypted:array',
  11 => 'encrypted:collection',
  12 => 'encrypted:json',
  13 => 'encrypted:object',
  14 => 'float',
  15 => 'hashed',
  16 => 'immutable_date',
  17 => 'immutable_datetime',
  18 => 'immutable_custom_datetime',
  19 => 'int',
  20 => 'integer',
  21 => 'json',
  22 => 'json:unicode',
  23 => 'object',
  24 => 'real',
  25 => 'string',
  26 => 'timestamp',
);
    protected $dateFormat = NULL;
    protected $appends = array (
);
    public static $snakeAttributes = true;
    protected static $mutatorCache = array (
);
    protected static $attributeMutatorCache = array (
);
    protected static $getAttributeMutatorCache = array (
);
    protected static $setAttributeMutatorCache = array (
);
    protected static $castTypeCache = array (
);
    public static $encrypter = NULL;
    protected function initializeHasAttributes() {}
    public function attributesToArray() {}
    protected function addDateAttributesToArray(array $attributes) {}
    protected function addMutatedAttributesToArray(array $attributes, array $mutatedAttributes) {}
    protected function addCastAttributesToArray(array $attributes, array $mutatedAttributes) {}
    protected function getArrayableAttributes() {}
    protected function getArrayableAppends() {}
    public function relationsToArray() {}
    protected function getArrayableRelations() {}
    protected function getArrayableItems(array $values) {}
    public function hasAttribute($key) {}
    public function getAttribute($key) {}
    protected function throwMissingAttributeExceptionIfApplicable($key) {}
    public function getAttributeValue($key) {}
    protected function getAttributeFromArray($key) {}
    public function getRelationValue($key) {}
    public function isRelation($key) {}
    protected function handleLazyLoadingViolation($key) {}
    protected function getRelationshipFromMethod($method) {}
    public function hasGetMutator($key) {}
    public function hasAttributeMutator($key) {}
    public function hasAttributeGetMutator($key) {}
    public function hasAnyGetMutator($key) {}
    protected function mutateAttribute($key, $value) {}
    protected function mutateAttributeMarkedAttribute($key, $value) {}
    protected function mutateAttributeForArray($key, $value) {}
    public function mergeCasts($casts) {}
    protected function ensureCastsAreStringValues($casts) {}
    protected function castAttribute($key, $value) {}
    protected function getClassCastableAttributeValue($key, $value) {}
    protected function getEnumCastableAttributeValue($key, $value) {}
    protected function getCastType($key) {}
    protected function deviateClassCastableAttribute($method, $key, $value) {}
    protected function serializeClassCastableAttribute($key, $value) {}
    protected function compareClassCastableAttribute($key, $original, $value) {}
    protected function isCustomDateTimeCast($cast) {}
    protected function isImmutableCustomDateTimeCast($cast) {}
    protected function isDecimalCast($cast) {}
    public function setAttribute($key, $value) {}
    public function hasSetMutator($key) {}
    public function hasAttributeSetMutator($key) {}
    protected function setMutatedAttributeValue($key, $value) {}
    protected function setAttributeMarkedMutatedAttributeValue($key, $value) {}
    protected function isDateAttribute($key) {}
    public function fillJsonAttribute($key, $value) {}
    protected function setClassCastableAttribute($key, $value) {}
    protected function setEnumCastableAttribute($key, $value) {}
    protected function getEnumCaseFromValue($enumClass, $value) {}
    protected function getStorableEnumValue($expectedEnum, $value) {}
    protected function getArrayAttributeWithValue($path, $key, $value) {}
    protected function getArrayAttributeByKey($key) {}
    protected function castAttributeAsJson($key, $value) {}
    protected function getJsonCastFlags($key) {}
    protected function asJson($value, $flags = 0) {}
    public function fromJson($value, $asObject = false) {}
    public function fromEncryptedString($value) {}
    protected function castAttributeAsEncryptedString($key, $value) {}
    public static function encryptUsing($encrypter) {}
    public static function currentEncrypter() {}
    protected function castAttributeAsHashedString($key, $value) {}
    public function fromFloat($value) {}
    protected function asDecimal($value, $decimals) {}
    protected function asDate($value) {}
    protected function asDateTime($value) {}
    protected function isStandardDateFormat($value) {}
    public function fromDateTime($value) {}
    protected function asTimestamp($value) {}
    protected function serializeDate(\DateTimeInterface $date) {}
    public function getDates() {}
    public function getDateFormat() {}
    public function setDateFormat($format) {}
    public function hasCast($key, $types = NULL) {}
    public function getCasts() {}
    protected function casts() {}
    protected function isDateCastable($key) {}
    protected function isDateCastableWithCustomFormat($key) {}
    protected function isJsonCastable($key) {}
    protected function isEncryptedCastable($key) {}
    protected function isClassCastable($key) {}
    protected function isEnumCastable($key) {}
    protected function isClassDeviable($key) {}
    protected function isClassSerializable($key) {}
    protected function isClassComparable($key) {}
    protected function resolveCasterClass($key) {}
    protected function parseCasterClass($class) {}
    protected function mergeAttributesFromCachedCasts() {}
    protected function mergeAttributeFromCachedCasts(string $key) {}
    protected function mergeAttributesFromClassCasts() {}
    protected function mergeAttributeFromClassCasts(string $key): void {}
    protected function mergeAttributesFromAttributeCasts() {}
    protected function mergeAttributeFromAttributeCasts(string $key): void {}
    protected function normalizeCastClassResponse($key, $value) {}
    public function getAttributes() {}
    protected function getAttributesForInsert() {}
    public function setRawAttributes(array $attributes, $sync = false) {}
    public function getOriginal($key = NULL, $default = NULL) {}
    protected function getOriginalWithoutRewindingModel($key = NULL, $default = NULL) {}
    public function getRawOriginal($key = NULL, $default = NULL) {}
    public function only($attributes) {}
    public function except($attributes) {}
    public function syncOriginal() {}
    public function syncOriginalAttribute($attribute) {}
    public function syncOriginalAttributes($attributes) {}
    public function syncChanges() {}
    public function isDirty($attributes = NULL) {}
    public function isClean($attributes = NULL) {}
    public function discardChanges() {}
    public function wasChanged($attributes = NULL) {}
    protected function hasChanges($changes, $attributes = NULL) {}
    public function getDirty() {}
    protected function getDirtyForUpdate() {}
    public function getChanges() {}
    public function getPrevious() {}
    public function originalIsEquivalent($key) {}
    protected function transformModelValue($key, $value) {}
    public function append($attributes) {}
    public function getAppends() {}
    public function setAppends(array $appends) {}
    public function mergeAppends(array $appends) {}
    public function hasAppended($attribute) {}
    public function withoutAppends() {}
    public function getMutatedAttributes() {}
    public static function cacheMutatedAttributes($classOrInstance) {}
    protected static function getMutatorMethods($class) {}
    protected static function getAttributeMarkedMutatorMethods($class) {}
}
