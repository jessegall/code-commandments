<?php

namespace Illuminate\Validation\Concerns;

trait ReplacesAttributes
{
    protected function replaceAcceptedIf($message, $attribute, $rule, $parameters) {}
    protected function replaceDeclinedIf($message, $attribute, $rule, $parameters) {}
    protected function replaceBetween($message, $attribute, $rule, $parameters) {}
    protected function replaceDateFormat($message, $attribute, $rule, $parameters) {}
    protected function replaceDecimal($message, $attribute, $rule, $parameters) {}
    protected function replaceDifferent($message, $attribute, $rule, $parameters) {}
    protected function replaceDigits($message, $attribute, $rule, $parameters) {}
    protected function replaceDigitsBetween($message, $attribute, $rule, $parameters) {}
    protected function replaceEncoding($message, $attribute, $rule, $parameters) {}
    protected function replaceExtensions($message, $attribute, $rule, $parameters) {}
    protected function replaceMin($message, $attribute, $rule, $parameters) {}
    protected function replaceMinDigits($message, $attribute, $rule, $parameters) {}
    protected function replaceMax($message, $attribute, $rule, $parameters) {}
    protected function replaceMaxDigits($message, $attribute, $rule, $parameters) {}
    protected function replaceMissingIf($message, $attribute, $rule, $parameters) {}
    protected function replaceMissingUnless($message, $attribute, $rule, $parameters) {}
    protected function replaceMissingWith($message, $attribute, $rule, $parameters) {}
    protected function replaceMissingWithAll($message, $attribute, $rule, $parameters) {}
    protected function replaceMultipleOf($message, $attribute, $rule, $parameters) {}
    protected function replaceIn($message, $attribute, $rule, $parameters) {}
    protected function replaceNotIn($message, $attribute, $rule, $parameters) {}
    protected function replaceInArray($message, $attribute, $rule, $parameters) {}
    protected function replaceInArrayKeys($message, $attribute, $rule, $parameters) {}
    protected function replaceRequiredArrayKeys($message, $attribute, $rule, $parameters) {}
    protected function replaceMimetypes($message, $attribute, $rule, $parameters) {}
    protected function replaceMimes($message, $attribute, $rule, $parameters) {}
    protected function replacePresentIf($message, $attribute, $rule, $parameters) {}
    protected function replacePresentUnless($message, $attribute, $rule, $parameters) {}
    protected function replacePresentWith($message, $attribute, $rule, $parameters) {}
    protected function replacePresentWithAll($message, $attribute, $rule, $parameters) {}
    protected function replaceRequiredWith($message, $attribute, $rule, $parameters) {}
    protected function replaceRequiredWithAll($message, $attribute, $rule, $parameters) {}
    protected function replaceRequiredWithout($message, $attribute, $rule, $parameters) {}
    protected function replaceRequiredWithoutAll($message, $attribute, $rule, $parameters) {}
    protected function replaceSize($message, $attribute, $rule, $parameters) {}
    protected function replaceGt($message, $attribute, $rule, $parameters) {}
    protected function replaceLt($message, $attribute, $rule, $parameters) {}
    protected function replaceGte($message, $attribute, $rule, $parameters) {}
    protected function replaceLte($message, $attribute, $rule, $parameters) {}
    protected function replaceRequiredIf($message, $attribute, $rule, $parameters) {}
    protected function replaceRequiredIfAccepted($message, $attribute, $rule, $parameters) {}
    protected function replaceRequiredIfDeclined($message, $attribute, $rule, $parameters) {}
    protected function replaceRequiredUnless($message, $attribute, $rule, $parameters) {}
    protected function replaceProhibitedIf($message, $attribute, $rule, $parameters) {}
    protected function replaceProhibitedIfAccepted($message, $attribute, $rule, $parameters) {}
    protected function replaceProhibitedIfDeclined($message, $attribute, $rule, $parameters) {}
    protected function replaceProhibitedUnless($message, $attribute, $rule, $parameters) {}
    protected function replaceProhibits($message, $attribute, $rule, $parameters) {}
    protected function replaceSame($message, $attribute, $rule, $parameters) {}
    protected function replaceBefore($message, $attribute, $rule, $parameters) {}
    protected function replaceBeforeOrEqual($message, $attribute, $rule, $parameters) {}
    protected function replaceAfter($message, $attribute, $rule, $parameters) {}
    protected function replaceAfterOrEqual($message, $attribute, $rule, $parameters) {}
    protected function replaceDateEquals($message, $attribute, $rule, $parameters) {}
    protected function replaceDimensions($message, $attribute, $rule, $parameters) {}
    protected function replaceEndsWith($message, $attribute, $rule, $parameters) {}
    protected function replaceDoesntEndWith($message, $attribute, $rule, $parameters) {}
    protected function replaceStartsWith($message, $attribute, $rule, $parameters) {}
    protected function replaceDoesntStartWith($message, $attribute, $rule, $parameters) {}
    protected function replaceDoesntContain($message, $attribute, $rule, $parameters) {}
    private function replaceWhileKeepingCase(string $message, array $mapping): string {}
}
