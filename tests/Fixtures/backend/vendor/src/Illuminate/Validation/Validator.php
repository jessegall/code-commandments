<?php

namespace Illuminate\Validation;

class Validator implements \Illuminate\Contracts\Validation\Validator, \Illuminate\Contracts\Support\MessageProvider
{
    use \Illuminate\Validation\Concerns\FormatsMessages, \Illuminate\Validation\Concerns\ValidatesAttributes;
    protected $translator = NULL;
    protected $container = NULL;
    protected $presenceVerifier = NULL;
    protected $failedRules = array (
);
    protected $excludeAttributes = array (
);
    protected $messages = NULL;
    protected $data = NULL;
    protected $initialRules = NULL;
    protected $rules = NULL;
    protected $currentRule = NULL;
    protected $implicitAttributes = array (
);
    protected $implicitAttributesFormatter = NULL;
    protected $distinctValues = array (
);
    protected $after = array (
);
    public $customMessages = array (
);
    public $fallbackMessages = array (
);
    public $customAttributes = array (
);
    public $customValues = array (
);
    protected $stopOnFirstFailure = false;
    public $excludeUnvalidatedArrayKeys = false;
    public $extensions = array (
);
    public $replacers = array (
);
    protected $fileRules = array (
  0 => 'Between',
  1 => 'Dimensions',
  2 => 'Encoding',
  3 => 'Extensions',
  4 => 'File',
  5 => 'Image',
  6 => 'Max',
  7 => 'Mimes',
  8 => 'Mimetypes',
  9 => 'Min',
  10 => 'Size',
);
    protected $implicitRules = array (
  0 => 'Accepted',
  1 => 'AcceptedIf',
  2 => 'Declined',
  3 => 'DeclinedIf',
  4 => 'Filled',
  5 => 'Missing',
  6 => 'MissingIf',
  7 => 'MissingUnless',
  8 => 'MissingWith',
  9 => 'MissingWithAll',
  10 => 'Present',
  11 => 'PresentIf',
  12 => 'PresentUnless',
  13 => 'PresentWith',
  14 => 'PresentWithAll',
  15 => 'Required',
  16 => 'RequiredIf',
  17 => 'RequiredIfAccepted',
  18 => 'RequiredIfDeclined',
  19 => 'RequiredUnless',
  20 => 'RequiredWith',
  21 => 'RequiredWithAll',
  22 => 'RequiredWithout',
  23 => 'RequiredWithoutAll',
);
    protected $dependentRules = array (
  0 => 'After',
  1 => 'AfterOrEqual',
  2 => 'Before',
  3 => 'BeforeOrEqual',
  4 => 'Confirmed',
  5 => 'Different',
  6 => 'ExcludeIf',
  7 => 'ExcludeUnless',
  8 => 'ExcludeWith',
  9 => 'ExcludeWithout',
  10 => 'Gt',
  11 => 'Gte',
  12 => 'Lt',
  13 => 'Lte',
  14 => 'AcceptedIf',
  15 => 'DeclinedIf',
  16 => 'RequiredIf',
  17 => 'RequiredIfAccepted',
  18 => 'RequiredIfDeclined',
  19 => 'RequiredUnless',
  20 => 'RequiredWith',
  21 => 'RequiredWithAll',
  22 => 'RequiredWithout',
  23 => 'RequiredWithoutAll',
  24 => 'PresentIf',
  25 => 'PresentUnless',
  26 => 'PresentWith',
  27 => 'PresentWithAll',
  28 => 'Prohibited',
  29 => 'ProhibitedIf',
  30 => 'ProhibitedIfAccepted',
  31 => 'ProhibitedIfDeclined',
  32 => 'ProhibitedUnless',
  33 => 'Prohibits',
  34 => 'MissingIf',
  35 => 'MissingUnless',
  36 => 'MissingWith',
  37 => 'MissingWithAll',
  38 => 'Same',
  39 => 'Unique',
);
    protected $excludeRules = array (
  0 => 'Exclude',
  1 => 'ExcludeIf',
  2 => 'ExcludeUnless',
  3 => 'ExcludeWith',
  4 => 'ExcludeWithout',
);
    protected $sizeRules = array (
  0 => 'Size',
  1 => 'Between',
  2 => 'Min',
  3 => 'Max',
  4 => 'Gt',
  5 => 'Lt',
  6 => 'Gte',
  7 => 'Lte',
);
    protected $numericRules = array (
  0 => 'Numeric',
  1 => 'Integer',
  2 => 'Decimal',
);
    protected $defaultNumericRules = array (
  0 => 'Numeric',
  1 => 'Integer',
  2 => 'Decimal',
);
    protected static $placeholderHash = NULL;
    protected $exception = 'Illuminate\\Validation\\ValidationException';
    protected $ensureExponentWithinAllowedRangeUsing = NULL;
    public function __construct(\Illuminate\Contracts\Translation\Translator $translator, array $data, array $rules, array $messages = array (
), array $attributes = array (
)) {}
    public function parseData(array $data) {}
    protected function replacePlaceholders($data) {}
    protected function replacePlaceholderInString(string $value) {}
    protected function replaceDotPlaceholderInParameters(array $parameters) {}
    public function after($callback) {}
    public function passes() {}
    public function fails() {}
    public function whenPasses(callable $callback, ?callable $default = NULL) {}
    public function whenFails(callable $callback, ?callable $default = NULL) {}
    protected function shouldBeExcluded($attribute) {}
    protected function removeAttribute($attribute) {}
    public function validate() {}
    public function validateWithBag(string $errorBag) {}
    public function safe(?array $keys = NULL) {}
    public function validated() {}
    protected function validateAttribute($attribute, $rule) {}
    protected function dependsOnOtherFields($rule) {}
    protected function getExplicitKeys($attribute) {}
    protected function getPrimaryAttribute($attribute) {}
    protected function replaceDotInParameters(array $parameters) {}
    protected function replaceAsterisksInParameters(array $parameters, array $keys) {}
    protected function isValidatable($rule, $attribute, $value) {}
    protected function presentOrRuleIsImplicit($rule, $attribute, $value) {}
    protected function isImplicit($rule) {}
    protected function passesOptionalCheck($attribute) {}
    protected function isNotNullIfMarkedAsNullable($rule, $attribute) {}
    protected function hasNotFailedPreviousRuleIfPresenceRule($rule, $attribute) {}
    protected function validateUsingCustomRule($attribute, $value, $rule) {}
    protected function shouldStopValidating($attribute) {}
    public function addFailure($attribute, $rule, $parameters = array (
)) {}
    protected function excludeAttribute(string $attribute) {}
    public function valid() {}
    public function invalid() {}
    protected function attributesThatHaveMessages() {}
    public function failed() {}
    public function messages() {}
    public function errors() {}
    public function getMessageBag() {}
    public function hasRule($attribute, $rules) {}
    protected function getRule($attribute, $rules) {}
    public function attributes() {}
    public function getData() {}
    public function setData(array $data) {}
    public function getValue($attribute) {}
    public function setValue($attribute, $value) {}
    public function getRules() {}
    public function getRulesWithoutPlaceholders() {}
    public function setRules(array $rules) {}
    public function appendRules(array $rules) {}
    public function addRules($rules) {}
    public function sometimes($attribute, $rules, callable $callback) {}
    private function dataForSometimesIteration(string $attribute, bool $removeLastSegmentOfAttribute) {}
    public function stopOnFirstFailure($stopOnFirstFailure = true) {}
    public function addExtensions(array $extensions) {}
    public function addImplicitExtensions(array $extensions) {}
    public function addDependentExtensions(array $extensions) {}
    public function addExtension($rule, $extension) {}
    public function addImplicitExtension($rule, $extension) {}
    public function addDependentExtension($rule, $extension) {}
    public function addReplacers(array $replacers) {}
    public function addReplacer($rule, $replacer) {}
    public function setCustomMessages(array $messages) {}
    public function setAttributeNames(array $attributes) {}
    public function addCustomAttributes(array $attributes) {}
    public function setImplicitAttributesFormatter(?callable $formatter = NULL) {}
    public function setValueNames(array $values) {}
    public function addCustomValues(array $customValues) {}
    public function setFallbackMessages(array $messages) {}
    public function getPresenceVerifier($connection = NULL) {}
    public function setPresenceVerifier(\Illuminate\Validation\PresenceVerifierInterface $presenceVerifier) {}
    public function getException() {}
    public function setException($exception) {}
    public function ensureExponentWithinAllowedRangeUsing($callback) {}
    public function getTranslator() {}
    public function setTranslator(\Illuminate\Contracts\Translation\Translator $translator) {}
    public function setContainer(\Illuminate\Contracts\Container\Container $container) {}
    protected function callExtension($rule, $parameters) {}
    protected function callClassBasedExtension($callback, $parameters) {}
    protected static function encodeAttributeWithPlaceholder(string $attribute) {}
    protected static function decodeAttributeWithPlaceholder(string $attribute) {}
    public static function flushState() {}
    public function __call($method, $parameters) {}
}
