<?php

namespace Illuminate\Validation\Concerns;

trait ReplacesAttributes
{
    /** @return string */
    protected function replaceAcceptedIf($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceDeclinedIf($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceBetween($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceDateFormat($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceDecimal($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceDifferent($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceDigits($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceDigitsBetween($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceEncoding($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceExtensions($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceMin($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceMinDigits($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceMax($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceMaxDigits($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceMissingIf($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceMissingUnless($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceMissingWith($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceMissingWithAll($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceMultipleOf($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceIn($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceNotIn($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceInArray($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceInArrayKeys($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceRequiredArrayKeys($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceMimetypes($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceMimes($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replacePresentIf($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replacePresentUnless($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replacePresentWith($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replacePresentWithAll($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceRequiredWith($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceRequiredWithAll($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceRequiredWithout($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceRequiredWithoutAll($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceSize($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceGt($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceLt($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceGte($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceLte($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceRequiredIf($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceRequiredIfAccepted($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceRequiredIfDeclined($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceRequiredUnless($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceProhibitedIf($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceProhibitedIfAccepted($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceProhibitedIfDeclined($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceProhibitedUnless($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceProhibits($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceSame($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceBefore($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceBeforeOrEqual($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceAfter($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceAfterOrEqual($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceDateEquals($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceDimensions($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceEndsWith($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceDoesntEndWith($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceStartsWith($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceDoesntStartWith($message, $attribute, $rule, $parameters) {}
    /** @return string */
    protected function replaceDoesntContain($message, $attribute, $rule, $parameters) {}
    private function replaceWhileKeepingCase(string $message, array $mapping): string {}
}
