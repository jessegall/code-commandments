<?php

namespace Illuminate\Validation\Concerns;

trait ValidatesAttributes
{
    public function validateAccepted($attribute, $value) {}
    public function validateAcceptedIf($attribute, $value, $parameters) {}
    public function validateDeclined($attribute, $value) {}
    public function validateDeclinedIf($attribute, $value, $parameters) {}
    public function validateActiveUrl($attribute, $value) {}
    protected function getDnsRecords($hostname, $type) {}
    public function validateAscii($attribute, $value) {}
    public function validateBail() {}
    public function validateBefore($attribute, $value, $parameters) {}
    public function validateBeforeOrEqual($attribute, $value, $parameters) {}
    public function validateAfter($attribute, $value, $parameters) {}
    public function validateAfterOrEqual($attribute, $value, $parameters) {}
    protected function compareDates($attribute, $value, $parameters, $operator) {}
    protected function getDateFormat($attribute) {}
    protected function getDateTimestamp($value) {}
    protected function checkDateTimeOrder($format, $first, $second, $operator) {}
    protected function getDateTimeWithOptionalFormat($format, $value) {}
    protected function getDateTime($value) {}
    public function validateAlpha($attribute, $value, $parameters) {}
    public function validateAlphaDash($attribute, $value, $parameters) {}
    public function validateAlphaNum($attribute, $value, $parameters) {}
    public function validateArray($attribute, $value, $parameters = array (
)) {}
    public function validateList($attribute, $value) {}
    public function validateRequiredArrayKeys($attribute, $value, $parameters) {}
    public function validateBetween($attribute, $value, $parameters) {}
    public function validateBoolean($attribute, $value, $parameters) {}
    public function validateConfirmed($attribute, $value, $parameters) {}
    public function validateContains($attribute, $value, $parameters) {}
    public function validateDoesntContain($attribute, $value, $parameters) {}
    protected function validateCurrentPassword($attribute, $value, $parameters) {}
    public function validateDate($attribute, $value) {}
    public function validateDateFormat($attribute, $value, $parameters) {}
    public function validateDateEquals($attribute, $value, $parameters) {}
    public function validateDecimal($attribute, $value, $parameters) {}
    public function validateDifferent($attribute, $value, $parameters) {}
    public function validateDigits($attribute, $value, $parameters) {}
    public function validateDigitsBetween($attribute, $value, $parameters) {}
    public function validateDimensions($attribute, $value, $parameters) {}
    protected function failsBasicDimensionChecks($parameters, $width, $height) {}
    protected function failsRatioCheck($parameters, $width, $height) {}
    private function failsMinRatioCheck($parameters, $width, $height) {}
    private function failsMaxRatioCheck($parameters, $width, $height) {}
    public function validateDistinct($attribute, $value, $parameters) {}
    protected function getDistinctValues($attribute) {}
    protected function extractDistinctValues($attribute) {}
    public function validateEmail($attribute, $value, $parameters) {}
    public function validateEncoding($attribute, $value, $parameters) {}
    public function validateExists($attribute, $value, $parameters) {}
    protected function getExistCount($connection, $table, $column, $value, $parameters) {}
    public function validateUnique($attribute, $value, $parameters) {}
    protected function getUniqueIds($idColumn, $parameters) {}
    protected function prepareUniqueId($id) {}
    protected function getUniqueExtra($parameters) {}
    public function parseTable($table) {}
    public function getQueryColumn($parameters, $attribute) {}
    public function guessColumnForQuery($attribute) {}
    protected function getExtraConditions(array $segments) {}
    public function validateExtensions($attribute, $value, $parameters) {}
    public function validateFile($attribute, $value) {}
    public function validateFilled($attribute, $value) {}
    public function validateGt($attribute, $value, $parameters) {}
    public function validateLt($attribute, $value, $parameters) {}
    public function validateGte($attribute, $value, $parameters) {}
    public function validateLte($attribute, $value, $parameters) {}
    public function validateLowercase($attribute, $value, $parameters) {}
    public function validateUppercase($attribute, $value, $parameters) {}
    public function validateHexColor($attribute, $value) {}
    public function validateImage($attribute, $value, $parameters = array (
)) {}
    public function validateIn($attribute, $value, $parameters) {}
    public function validateInArray($attribute, $value, $parameters) {}
    public function validateInArrayKeys($attribute, $value, $parameters) {}
    public function validateInteger($attribute, $value, array $parameters = array (
)) {}
    public function validateIp($attribute, $value) {}
    public function validateIpv4($attribute, $value) {}
    public function validateIpv6($attribute, $value) {}
    public function validateMacAddress($attribute, $value) {}
    public function validateJson($attribute, $value) {}
    public function validateMax($attribute, $value, $parameters) {}
    public function validateMaxDigits($attribute, $value, $parameters) {}
    public function validateMimes($attribute, $value, $parameters) {}
    public function validateMimetypes($attribute, $value, $parameters) {}
    protected function shouldBlockPhpUpload($value, $parameters) {}
    public function validateMin($attribute, $value, $parameters) {}
    public function validateMinDigits($attribute, $value, $parameters) {}
    public function validateMissing($attribute, $value, $parameters) {}
    public function validateMissingIf($attribute, $value, $parameters) {}
    public function validateMissingUnless($attribute, $value, $parameters) {}
    public function validateMissingWith($attribute, $value, $parameters) {}
    public function validateMissingWithAll($attribute, $value, $parameters) {}
    public function validateMultipleOf($attribute, $value, $parameters) {}
    public function validateNullable() {}
    public function validateNotIn($attribute, $value, $parameters) {}
    public function validateNumeric($attribute, $value, array $parameters) {}
    public function validatePresent($attribute, $value) {}
    public function validatePresentIf($attribute, $value, $parameters) {}
    public function validatePresentUnless($attribute, $value, $parameters) {}
    public function validatePresentWith($attribute, $value, $parameters) {}
    public function validatePresentWithAll($attribute, $value, $parameters) {}
    public function validateRegex($attribute, $value, $parameters) {}
    public function validateNotRegex($attribute, $value, $parameters) {}
    public function validateRequired($attribute, $value) {}
    public function validateRequiredIf($attribute, $value, $parameters) {}
    public function validateRequiredIfAccepted($attribute, $value, $parameters) {}
    public function validateRequiredIfDeclined($attribute, $value, $parameters) {}
    public function validateProhibited($attribute, $value) {}
    public function validateProhibitedIf($attribute, $value, $parameters) {}
    public function validateProhibitedIfAccepted($attribute, $value, $parameters) {}
    public function validateProhibitedIfDeclined($attribute, $value, $parameters) {}
    public function validateProhibitedUnless($attribute, $value, $parameters) {}
    public function validateProhibits($attribute, $value, $parameters) {}
    public function validateExclude() {}
    public function validateExcludeIf($attribute, $value, $parameters) {}
    public function validateExcludeUnless($attribute, $value, $parameters) {}
    public function validateRequiredUnless($attribute, $value, $parameters) {}
    public function validateExcludeWith($attribute, $value, $parameters) {}
    public function validateExcludeWithout($attribute, $value, $parameters) {}
    public function parseDependentRuleParameters($parameters) {}
    protected function shouldConvertToBoolean($parameter) {}
    protected function convertValuesToBoolean($values) {}
    protected function convertValuesToNull($values) {}
    public function validateRequiredWith($attribute, $value, $parameters) {}
    public function validateRequiredWithAll($attribute, $value, $parameters) {}
    public function validateRequiredWithout($attribute, $value, $parameters) {}
    public function validateRequiredWithoutAll($attribute, $value, $parameters) {}
    protected function anyFailingRequired(array $attributes) {}
    protected function allFailingRequired(array $attributes) {}
    public function validateSame($attribute, $value, $parameters) {}
    public function validateSize($attribute, $value, $parameters) {}
    public function validateSometimes() {}
    public function validateStartsWith($attribute, $value, $parameters) {}
    public function validateDoesntStartWith($attribute, $value, $parameters) {}
    public function validateEndsWith($attribute, $value, $parameters) {}
    public function validateDoesntEndWith($attribute, $value, $parameters) {}
    public function validateString($attribute, $value) {}
    public function validateTimezone($attribute, $value, $parameters = array (
)) {}
    public function validateUrl($attribute, $value, $parameters = array (
)) {}
    public function validateUlid($attribute, $value) {}
    public function validateUuid($attribute, $value, $parameters) {}
    protected function getSize($attribute, $value) {}
    public function isValidFileInstance($value) {}
    protected function compare($first, $second, $operator) {}
    public function parseNamedParameters($parameters) {}
    public function requireParameterCount($count, $parameters, $rule) {}
    protected function isSameType($first, $second) {}
    protected function shouldBeNumeric($attribute, $rule) {}
    protected function trim($value) {}
    protected function ensureExponentWithinAllowedRange($attribute, $value) {}
}
