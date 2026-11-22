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
    /** @return void */
    protected function initializeHasAttributes() {}
    /** @return array<string, */
    public function attributesToArray() {}
    /** @return array<string, */
    protected function addDateAttributesToArray(array $attributes) {}
    /** @return array<string, */
    protected function addMutatedAttributesToArray(array $attributes, array $mutatedAttributes) {}
    /** @return array<string, */
    protected function addCastAttributesToArray(array $attributes, array $mutatedAttributes) {}
    /** @return array<string, */
    protected function getArrayableAttributes() {}
    /** @return array */
    protected function getArrayableAppends() {}
    /** @return array */
    public function relationsToArray() {}
    /** @return array */
    protected function getArrayableRelations() {}
    /** @return array */
    protected function getArrayableItems(array $values) {}
    /** @return bool */
    public function hasAttribute($key) {}
    /** @return mixed */
    public function getAttribute($key) {}
    /** @return null */
    protected function throwMissingAttributeExceptionIfApplicable($key) {}
    /** @return mixed */
    public function getAttributeValue($key) {}
    /** @return mixed */
    protected function getAttributeFromArray($key) {}
    /** @return mixed */
    public function getRelationValue($key) {}
    /** @return bool */
    public function isRelation($key) {}
    /** @return mixed */
    protected function handleLazyLoadingViolation($key) {}
    /** @return mixed */
    protected function getRelationshipFromMethod($method) {}
    /** @return bool */
    public function hasGetMutator($key) {}
    /** @return bool */
    public function hasAttributeMutator($key) {}
    /** @return bool */
    public function hasAttributeGetMutator($key) {}
    /** @return bool */
    public function hasAnyGetMutator($key) {}
    /** @return mixed */
    protected function mutateAttribute($key, $value) {}
    /** @return mixed */
    protected function mutateAttributeMarkedAttribute($key, $value) {}
    /** @return mixed */
    protected function mutateAttributeForArray($key, $value) {}
    /** @return $this */
    public function mergeCasts($casts) {}
    /** @return array */
    protected function ensureCastsAreStringValues($casts) {}
    /** @return mixed */
    protected function castAttribute($key, $value) {}
    /** @return mixed */
    protected function getClassCastableAttributeValue($key, $value) {}
    /** @return mixed */
    protected function getEnumCastableAttributeValue($key, $value) {}
    /** @return string */
    protected function getCastType($key) {}
    /** @return mixed */
    protected function deviateClassCastableAttribute($method, $key, $value) {}
    /** @return mixed */
    protected function serializeClassCastableAttribute($key, $value) {}
    /** @return bool */
    protected function compareClassCastableAttribute($key, $original, $value) {}
    /** @return bool */
    protected function isCustomDateTimeCast($cast) {}
    /** @return bool */
    protected function isImmutableCustomDateTimeCast($cast) {}
    /** @return bool */
    protected function isDecimalCast($cast) {}
    /** @return mixed */
    public function setAttribute($key, $value) {}
    /** @return bool */
    public function hasSetMutator($key) {}
    /** @return bool */
    public function hasAttributeSetMutator($key) {}
    /** @return mixed */
    protected function setMutatedAttributeValue($key, $value) {}
    /** @return mixed */
    protected function setAttributeMarkedMutatedAttributeValue($key, $value) {}
    /** @return bool */
    protected function isDateAttribute($key) {}
    /** @return $this */
    public function fillJsonAttribute($key, $value) {}
    /** @return void */
    protected function setClassCastableAttribute($key, $value) {}
    /** @return void */
    protected function setEnumCastableAttribute($key, $value) {}
    /** @return \UnitEnum */
    protected function getEnumCaseFromValue($enumClass, $value) {}
    /** @return string|int */
    protected function getStorableEnumValue($expectedEnum, $value) {}
    /** @return array */
    protected function getArrayAttributeWithValue($path, $key, $value) {}
    /** @return array */
    protected function getArrayAttributeByKey($key) {}
    /** @return string */
    protected function castAttributeAsJson($key, $value) {}
    /** @return int */
    protected function getJsonCastFlags($key) {}
    /** @return string */
    protected function asJson($value, $flags = 0) {}
    /** @return mixed */
    public function fromJson($value, $asObject = false) {}
    /** @return mixed */
    public function fromEncryptedString($value) {}
    /** @return string */
    protected function castAttributeAsEncryptedString($key, $value) {}
    /** @return void */
    public static function encryptUsing($encrypter) {}
    /** @return \Illuminate\Contracts\Encryption\Encrypter */
    public static function currentEncrypter() {}
    /** @return string */
    protected function castAttributeAsHashedString($key, $value) {}
    /** @return mixed */
    public function fromFloat($value) {}
    /** @return string */
    protected function asDecimal($value, $decimals) {}
    /** @return \Illuminate\Support\Carbon */
    protected function asDate($value) {}
    /** @return \Illuminate\Support\Carbon */
    protected function asDateTime($value) {}
    /** @return bool */
    protected function isStandardDateFormat($value) {}
    /** @return string|null */
    public function fromDateTime($value) {}
    /** @return int */
    protected function asTimestamp($value) {}
    /** @return string */
    protected function serializeDate(\DateTimeInterface $date) {}
    /** @return array<int, */
    public function getDates() {}
    /** @return string */
    public function getDateFormat() {}
    /** @return $this */
    public function setDateFormat($format) {}
    /** @return bool */
    public function hasCast($key, $types = NULL) {}
    /** @return array */
    public function getCasts() {}
    /** @return array<string, */
    protected function casts() {}
    /** @return bool */
    protected function isDateCastable($key) {}
    /** @return bool */
    protected function isDateCastableWithCustomFormat($key) {}
    /** @return bool */
    protected function isJsonCastable($key) {}
    /** @return bool */
    protected function isEncryptedCastable($key) {}
    /** @return bool */
    protected function isClassCastable($key) {}
    /** @return bool */
    protected function isEnumCastable($key) {}
    /** @return bool */
    protected function isClassDeviable($key) {}
    /** @return bool */
    protected function isClassSerializable($key) {}
    /** @return bool */
    protected function isClassComparable($key) {}
    /** @return mixed */
    protected function resolveCasterClass($key) {}
    /** @return string */
    protected function parseCasterClass($class) {}
    /** @return void */
    protected function mergeAttributesFromCachedCasts() {}
    /** @return void */
    protected function mergeAttributeFromCachedCasts(string $key) {}
    /** @return void */
    protected function mergeAttributesFromClassCasts() {}
    protected function mergeAttributeFromClassCasts(string $key): void {}
    /** @return void */
    protected function mergeAttributesFromAttributeCasts() {}
    protected function mergeAttributeFromAttributeCasts(string $key): void {}
    /** @return array */
    protected function normalizeCastClassResponse($key, $value) {}
    /** @return array<string, */
    public function getAttributes() {}
    /** @return array */
    protected function getAttributesForInsert() {}
    /** @return $this */
    public function setRawAttributes(array $attributes, $sync = false) {}
    /** @return ($key */
    public function getOriginal($key = NULL, $default = NULL) {}
    /** @return ($key */
    protected function getOriginalWithoutRewindingModel($key = NULL, $default = NULL) {}
    /** @return ($key */
    public function getRawOriginal($key = NULL, $default = NULL) {}
    /** @return array<string, */
    public function only($attributes) {}
    /** @return array */
    public function except($attributes) {}
    /** @return $this */
    public function syncOriginal() {}
    /** @return $this */
    public function syncOriginalAttribute($attribute) {}
    /** @return $this */
    public function syncOriginalAttributes($attributes) {}
    /** @return $this */
    public function syncChanges() {}
    /** @return bool */
    public function isDirty($attributes = NULL) {}
    /** @return bool */
    public function isClean($attributes = NULL) {}
    /** @return $this */
    public function discardChanges() {}
    /** @return bool */
    public function wasChanged($attributes = NULL) {}
    /** @return bool */
    protected function hasChanges($changes, $attributes = NULL) {}
    /** @return array<string, */
    public function getDirty() {}
    /** @return array<string, */
    protected function getDirtyForUpdate() {}
    /** @return array<string, */
    public function getChanges() {}
    /** @return array<string, */
    public function getPrevious() {}
    /** @return bool */
    public function originalIsEquivalent($key) {}
    /** @return mixed */
    protected function transformModelValue($key, $value) {}
    /** @return $this */
    public function append($attributes) {}
    /** @return array */
    public function getAppends() {}
    /** @return $this */
    public function setAppends(array $appends) {}
    /** @return $this */
    public function mergeAppends(array $appends) {}
    /** @return bool */
    public function hasAppended($attribute) {}
    /** @return $this */
    public function withoutAppends() {}
    /** @return array */
    public function getMutatedAttributes() {}
    /** @return void */
    public static function cacheMutatedAttributes($classOrInstance) {}
    /** @return array */
    protected static function getMutatorMethods($class) {}
    /** @return array */
    protected static function getAttributeMarkedMutatorMethods($class) {}
}
