<?php

namespace Illuminate\Validation\Concerns;

trait ValidatesAttributes
{
    /** @return bool */
    public function validateAccepted($attribute, $value) {}
    /** @return bool */
    public function validateAcceptedIf($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateDeclined($attribute, $value) {}
    /** @return bool */
    public function validateDeclinedIf($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateActiveUrl($attribute, $value) {}
    /** @return array|false */
    protected function getDnsRecords($hostname, $type) {}
    /** @return bool */
    public function validateAscii($attribute, $value) {}
    /** @return bool */
    public function validateBail() {}
    /** @return bool */
    public function validateBefore($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateBeforeOrEqual($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateAfter($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateAfterOrEqual($attribute, $value, $parameters) {}
    /** @return bool */
    protected function compareDates($attribute, $value, $parameters, $operator) {}
    /** @return string|null */
    protected function getDateFormat($attribute) {}
    /** @return int|null */
    protected function getDateTimestamp($value) {}
    /** @return bool */
    protected function checkDateTimeOrder($format, $first, $second, $operator) {}
    /** @return \DateTime|null */
    protected function getDateTimeWithOptionalFormat($format, $value) {}
    /** @return \DateTime|null */
    protected function getDateTime($value) {}
    /** @return bool */
    public function validateAlpha($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateAlphaDash($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateAlphaNum($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateArray($attribute, $value, $parameters = array (
)) {}
    /** @return bool */
    public function validateList($attribute, $value) {}
    /** @return bool */
    public function validateRequiredArrayKeys($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateBetween($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateBoolean($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateConfirmed($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateContains($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateDoesntContain($attribute, $value, $parameters) {}
    /** @return bool */
    protected function validateCurrentPassword($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateDate($attribute, $value) {}
    /** @return bool */
    public function validateDateFormat($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateDateEquals($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateDecimal($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateDifferent($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateDigits($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateDigitsBetween($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateDimensions($attribute, $value, $parameters) {}
    /** @return bool */
    protected function failsBasicDimensionChecks($parameters, $width, $height) {}
    /** @return bool */
    protected function failsRatioCheck($parameters, $width, $height) {}
    /** @return bool */
    private function failsMinRatioCheck($parameters, $width, $height) {}
    /** @return bool */
    private function failsMaxRatioCheck($parameters, $width, $height) {}
    /** @return bool */
    public function validateDistinct($attribute, $value, $parameters) {}
    /** @return array */
    protected function getDistinctValues($attribute) {}
    /** @return array */
    protected function extractDistinctValues($attribute) {}
    /** @return bool */
    public function validateEmail($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateEncoding($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateExists($attribute, $value, $parameters) {}
    /** @return int */
    protected function getExistCount($connection, $table, $column, $value, $parameters) {}
    /** @return bool */
    public function validateUnique($attribute, $value, $parameters) {}
    /** @return array */
    protected function getUniqueIds($idColumn, $parameters) {}
    /** @return int */
    protected function prepareUniqueId($id) {}
    /** @return array */
    protected function getUniqueExtra($parameters) {}
    /** @return array */
    public function parseTable($table) {}
    /** @return int|string */
    public function getQueryColumn($parameters, $attribute) {}
    /** @return string */
    public function guessColumnForQuery($attribute) {}
    /** @return array */
    protected function getExtraConditions(array $segments) {}
    /** @return bool */
    public function validateExtensions($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateFile($attribute, $value) {}
    /** @return bool */
    public function validateFilled($attribute, $value) {}
    /** @return bool */
    public function validateGt($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateLt($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateGte($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateLte($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateLowercase($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateUppercase($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateHexColor($attribute, $value) {}
    /** @return bool */
    public function validateImage($attribute, $value, $parameters = array (
)) {}
    /** @return bool */
    public function validateIn($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateInArray($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateInArrayKeys($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateInteger($attribute, $value, array $parameters = array (
)) {}
    /** @return bool */
    public function validateIp($attribute, $value) {}
    /** @return bool */
    public function validateIpv4($attribute, $value) {}
    /** @return bool */
    public function validateIpv6($attribute, $value) {}
    /** @return bool */
    public function validateMacAddress($attribute, $value) {}
    /** @return bool */
    public function validateJson($attribute, $value) {}
    /** @return bool */
    public function validateMax($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateMaxDigits($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateMimes($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateMimetypes($attribute, $value, $parameters) {}
    /** @return bool */
    protected function shouldBlockPhpUpload($value, $parameters) {}
    /** @return bool */
    public function validateMin($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateMinDigits($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateMissing($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateMissingIf($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateMissingUnless($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateMissingWith($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateMissingWithAll($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateMultipleOf($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateNullable() {}
    /** @return bool */
    public function validateNotIn($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateNumeric($attribute, $value, array $parameters) {}
    /** @return bool */
    public function validatePresent($attribute, $value) {}
    /** @return bool */
    public function validatePresentIf($attribute, $value, $parameters) {}
    /** @return bool */
    public function validatePresentUnless($attribute, $value, $parameters) {}
    /** @return bool */
    public function validatePresentWith($attribute, $value, $parameters) {}
    /** @return bool */
    public function validatePresentWithAll($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateRegex($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateNotRegex($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateRequired($attribute, $value) {}
    /** @return bool */
    public function validateRequiredIf($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateRequiredIfAccepted($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateRequiredIfDeclined($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateProhibited($attribute, $value) {}
    /** @return bool */
    public function validateProhibitedIf($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateProhibitedIfAccepted($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateProhibitedIfDeclined($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateProhibitedUnless($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateProhibits($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateExclude() {}
    /** @return bool */
    public function validateExcludeIf($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateExcludeUnless($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateRequiredUnless($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateExcludeWith($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateExcludeWithout($attribute, $value, $parameters) {}
    /** @return array */
    public function parseDependentRuleParameters($parameters) {}
    /** @return bool */
    protected function shouldConvertToBoolean($parameter) {}
    /** @return array */
    protected function convertValuesToBoolean($values) {}
    /** @return array */
    protected function convertValuesToNull($values) {}
    /** @return bool */
    public function validateRequiredWith($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateRequiredWithAll($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateRequiredWithout($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateRequiredWithoutAll($attribute, $value, $parameters) {}
    /** @return bool */
    protected function anyFailingRequired(array $attributes) {}
    /** @return bool */
    protected function allFailingRequired(array $attributes) {}
    /** @return bool */
    public function validateSame($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateSize($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateSometimes() {}
    /** @return bool */
    public function validateStartsWith($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateDoesntStartWith($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateEndsWith($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateDoesntEndWith($attribute, $value, $parameters) {}
    /** @return bool */
    public function validateString($attribute, $value) {}
    /** @return bool */
    public function validateTimezone($attribute, $value, $parameters = array (
)) {}
    /** @return bool */
    public function validateUrl($attribute, $value, $parameters = array (
)) {}
    /** @return bool */
    public function validateUlid($attribute, $value) {}
    /** @return bool */
    public function validateUuid($attribute, $value, $parameters) {}
    /** @return int|string */
    protected function getSize($attribute, $value) {}
    /** @return bool */
    public function isValidFileInstance($value) {}
    /** @return bool */
    protected function compare($first, $second, $operator) {}
    /** @return array */
    public function parseNamedParameters($parameters) {}
    /** @return void */
    public function requireParameterCount($count, $parameters, $rule) {}
    /** @return bool */
    protected function isSameType($first, $second) {}
    /** @return void */
    protected function shouldBeNumeric($attribute, $rule) {}
    /** @return string */
    protected function trim($value) {}
    /** @return mixed */
    protected function ensureExponentWithinAllowedRange($attribute, $value) {}
}
