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
    /** @return array */
    public function parseData(array $data) {}
    /** @return array */
    protected function replacePlaceholders($data) {}
    /** @return string */
    protected function replacePlaceholderInString(string $value) {}
    /** @return array */
    protected function replaceDotPlaceholderInParameters(array $parameters) {}
    /** @return $this */
    public function after($callback) {}
    /** @return bool */
    public function passes() {}
    /** @return bool */
    public function fails() {}
    /** @return $this|TWhenReturnType */
    public function whenPasses(callable $callback, ?callable $default = NULL) {}
    /** @return $this|TWhenReturnType */
    public function whenFails(callable $callback, ?callable $default = NULL) {}
    /** @return bool */
    protected function shouldBeExcluded($attribute) {}
    /** @return void */
    protected function removeAttribute($attribute) {}
    /** @return array */
    public function validate() {}
    /** @return array */
    public function validateWithBag(string $errorBag) {}
    /** @return \Illuminate\Support\ValidatedInput|array */
    public function safe(?array $keys = NULL) {}
    /** @return array */
    public function validated() {}
    /** @return void */
    protected function validateAttribute($attribute, $rule) {}
    /** @return bool */
    protected function dependsOnOtherFields($rule) {}
    /** @return array */
    protected function getExplicitKeys($attribute) {}
    /** @return string */
    protected function getPrimaryAttribute($attribute) {}
    /** @return array */
    protected function replaceDotInParameters(array $parameters) {}
    /** @return array */
    protected function replaceAsterisksInParameters(array $parameters, array $keys) {}
    /** @return bool */
    protected function isValidatable($rule, $attribute, $value) {}
    /** @return bool */
    protected function presentOrRuleIsImplicit($rule, $attribute, $value) {}
    /** @return bool */
    protected function isImplicit($rule) {}
    /** @return bool */
    protected function passesOptionalCheck($attribute) {}
    /** @return bool */
    protected function isNotNullIfMarkedAsNullable($rule, $attribute) {}
    /** @return bool */
    protected function hasNotFailedPreviousRuleIfPresenceRule($rule, $attribute) {}
    /** @return void */
    protected function validateUsingCustomRule($attribute, $value, $rule) {}
    /** @return bool */
    protected function shouldStopValidating($attribute) {}
    /** @return void */
    public function addFailure($attribute, $rule, $parameters = array (
)) {}
    /** @return void */
    protected function excludeAttribute(string $attribute) {}
    /** @return array */
    public function valid() {}
    /** @return array */
    public function invalid() {}
    /** @return array */
    protected function attributesThatHaveMessages() {}
    /** @return array */
    public function failed() {}
    /** @return \Illuminate\Support\MessageBag */
    public function messages() {}
    /** @return \Illuminate\Support\MessageBag */
    public function errors() {}
    /** @return \Illuminate\Support\MessageBag */
    public function getMessageBag() {}
    /** @return bool */
    public function hasRule($attribute, $rules) {}
    /** @return array|null */
    protected function getRule($attribute, $rules) {}
    /** @return array */
    public function attributes() {}
    /** @return array */
    public function getData() {}
    /** @return $this */
    public function setData(array $data) {}
    /** @return mixed */
    public function getValue($attribute) {}
    /** @return void */
    public function setValue($attribute, $value) {}
    /** @return array */
    public function getRules() {}
    /** @return array */
    public function getRulesWithoutPlaceholders() {}
    /** @return $this */
    public function setRules(array $rules) {}
    /** @return $this */
    public function appendRules(array $rules) {}
    /** @return void */
    public function addRules($rules) {}
    /** @return $this */
    public function sometimes($attribute, $rules, callable $callback) {}
    /** @return \Illuminate\Support\Fluent|mixed */
    private function dataForSometimesIteration(string $attribute, bool $removeLastSegmentOfAttribute) {}
    /** @return $this */
    public function stopOnFirstFailure($stopOnFirstFailure = true) {}
    /** @return void */
    public function addExtensions(array $extensions) {}
    /** @return void */
    public function addImplicitExtensions(array $extensions) {}
    /** @return void */
    public function addDependentExtensions(array $extensions) {}
    /** @return void */
    public function addExtension($rule, $extension) {}
    /** @return void */
    public function addImplicitExtension($rule, $extension) {}
    /** @return void */
    public function addDependentExtension($rule, $extension) {}
    /** @return void */
    public function addReplacers(array $replacers) {}
    /** @return void */
    public function addReplacer($rule, $replacer) {}
    /** @return $this */
    public function setCustomMessages(array $messages) {}
    /** @return $this */
    public function setAttributeNames(array $attributes) {}
    /** @return $this */
    public function addCustomAttributes(array $attributes) {}
    /** @return $this */
    public function setImplicitAttributesFormatter(?callable $formatter = NULL) {}
    /** @return $this */
    public function setValueNames(array $values) {}
    /** @return $this */
    public function addCustomValues(array $customValues) {}
    /** @return void */
    public function setFallbackMessages(array $messages) {}
    /** @return \Illuminate\Validation\PresenceVerifierInterface */
    public function getPresenceVerifier($connection = NULL) {}
    /** @return void */
    public function setPresenceVerifier(\Illuminate\Validation\PresenceVerifierInterface $presenceVerifier) {}
    /** @return class-string<\Illuminate\Validation\ValidationException> */
    public function getException() {}
    /** @return $this */
    public function setException($exception) {}
    /** @return $this */
    public function ensureExponentWithinAllowedRangeUsing($callback) {}
    /** @return \Illuminate\Contracts\Translation\Translator */
    public function getTranslator() {}
    /** @return void */
    public function setTranslator(\Illuminate\Contracts\Translation\Translator $translator) {}
    /** @return void */
    public function setContainer(\Illuminate\Contracts\Container\Container $container) {}
    /** @return bool|null */
    protected function callExtension($rule, $parameters) {}
    /** @return bool */
    protected function callClassBasedExtension($callback, $parameters) {}
    /** @return string */
    protected static function encodeAttributeWithPlaceholder(string $attribute) {}
    /** @return string */
    protected static function decodeAttributeWithPlaceholder(string $attribute) {}
    /** @return void */
    public static function flushState() {}
    /** @return mixed */
    public function __call($method, $parameters) {}
}
