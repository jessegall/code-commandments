<?php

namespace Illuminate\Support;

class Str
{
    use \Illuminate\Support\Traits\Macroable;
    public const INVISIBLE_CHARACTERS = '\\x{0009}\\x{0020}\\x{00A0}\\x{00AD}\\x{034F}\\x{061C}\\x{115F}\\x{1160}\\x{17B4}\\x{17B5}\\x{180E}\\x{2000}\\x{2001}\\x{2002}\\x{2003}\\x{2004}\\x{2005}\\x{2006}\\x{2007}\\x{2008}\\x{2009}\\x{200A}\\x{200B}\\x{200C}\\x{200D}\\x{200E}\\x{200F}\\x{202F}\\x{205F}\\x{2060}\\x{2061}\\x{2062}\\x{2063}\\x{2064}\\x{2065}\\x{206A}\\x{206B}\\x{206C}\\x{206D}\\x{206E}\\x{206F}\\x{3000}\\x{2800}\\x{3164}\\x{FEFF}\\x{FFA0}\\x{1D159}\\x{1D173}\\x{1D174}\\x{1D175}\\x{1D176}\\x{1D177}\\x{1D178}\\x{1D179}\\x{1D17A}\\x{E0020}';
    protected static $snakeCache = array (
);
    protected static $camelCache = array (
);
    protected static $studlyCache = array (
);
    protected static $uuidFactory = NULL;
    protected static $ulidFactory = NULL;
    protected static $randomStringFactory = NULL;
    /** @return \Illuminate\Support\Stringable */
    public static function of($string) {}
    /** @return string */
    public static function after($subject, $search) {}
    /** @return string */
    public static function afterLast($subject, $search) {}
    /** @return string */
    public static function ascii($value, $language = 'en') {}
    /** @return string */
    public static function transliterate($string, $unknown = '?', $strict = false) {}
    /** @return string */
    public static function before($subject, $search) {}
    /** @return string */
    public static function beforeLast($subject, $search) {}
    /** @return string */
    public static function between($subject, $from, $to) {}
    /** @return string */
    public static function betweenFirst($subject, $from, $to) {}
    /** @return ($value */
    public static function camel($value) {}
    /** @return string|false */
    public static function charAt($subject, $index) {}
    /** @return string */
    public static function chopStart($subject, $needle) {}
    /** @return string */
    public static function chopEnd($subject, $needle) {}
    /** @return ($needles */
    public static function contains($haystack, $needles, $ignoreCase = false) {}
    /** @return ($needles */
    public static function containsAll($haystack, $needles, $ignoreCase = false) {}
    /** @return ($needles */
    public static function doesntContain($haystack, $needles, $ignoreCase = false) {}
    /** @return ($string */
    public static function convertCase(string $string, int $mode = \Illuminate\Support\MB_CASE_FOLD, ?string $encoding = 'UTF-8') {}
    /** @return ($string */
    public static function deduplicate(string $string, array|string $characters = ' ') {}
    /** @return ($needles */
    public static function endsWith($haystack, $needles) {}
    /** @return ($needles */
    public static function doesntEndWith($haystack, $needles) {}
    /** @return string|null */
    public static function excerpt($text, $phrase = '', $options = array (
)) {}
    /** @return ($value */
    public static function finish($value, $cap) {}
    /** @return ($value */
    public static function wrap($value, $before, $after = NULL) {}
    /** @return string */
    public static function unwrap($value, $before, $after = NULL) {}
    /** @return bool */
    public static function is($pattern, $value, $ignoreCase = false) {}
    /** @return bool */
    public static function isAscii($value) {}
    /** @return bool */
    public static function isJson($value) {}
    /** @return bool */
    public static function isUrl($value, array $protocols = array (
)) {}
    /** @return bool */
    public static function isUuid($value, $version = NULL) {}
    /** @return bool */
    public static function isUlid($value) {}
    /** @return ($value */
    public static function kebab($value) {}
    /** @return non-negative-int */
    public static function length($value, $encoding = NULL) {}
    /** @return string */
    public static function limit($value, $limit = 100, $end = '...', $preserveWords = false) {}
    /** @return ($value */
    public static function lower($value) {}
    /** @return string */
    public static function words($value, $words = 100, $end = '...') {}
    /** @return ($string */
    public static function markdown($string, array $options = array (
), array $extensions = array (
)) {}
    /** @return ($string */
    public static function inlineMarkdown($string, array $options = array (
), array $extensions = array (
)) {}
    /** @return string */
    public static function mask($string, $character, $index, $length = NULL, $encoding = 'UTF-8') {}
    /** @return string */
    public static function match($pattern, $subject) {}
    /** @return ($pattern */
    public static function isMatch($pattern, $value) {}
    /** @return \Illuminate\Support\Collection */
    public static function matchAll($pattern, $subject) {}
    /** @return string */
    public static function numbers($value) {}
    /** @return string */
    public static function padBoth($value, $length, $pad = ' ') {}
    /** @return string */
    public static function padLeft($value, $length, $pad = ' ') {}
    /** @return string */
    public static function padRight($value, $length, $pad = ' ') {}
    /** @return array<int, */
    public static function parseCallback($callback, $default = NULL) {}
    /** @return string */
    public static function plural($value, $count = 2, $prependCount = false) {}
    /** @return string */
    public static function pluralStudly($value, $count = 2) {}
    /** @return string */
    public static function pluralPascal($value, $count = 2) {}
    /** @return ($letters */
    public static function password($length = 32, $letters = true, $numbers = true, $symbols = true, $spaces = false) {}
    /** @return ($haystack */
    public static function position($haystack, $needle, $offset = 0, $encoding = NULL) {}
    /** @return string */
    public static function random($length = 16) {}
    /** @return void */
    public static function createRandomStringsUsing(?callable $factory = NULL) {}
    /** @return void */
    public static function createRandomStringsUsingSequence(array $sequence, $whenMissing = NULL) {}
    /** @return void */
    public static function createRandomStringsNormally() {}
    /** @return string */
    public static function repeat(string $string, int $times) {}
    /** @return string */
    public static function replaceArray($search, $replace, $subject) {}
    /** @return string */
    private static function toStringOr($value, $fallback) {}
    /** @return ($subject */
    public static function replace($search, $replace, $subject, $caseSensitive = true) {}
    /** @return string */
    public static function replaceFirst($search, $replace, $subject) {}
    /** @return string */
    public static function replaceStart($search, $replace, $subject) {}
    /** @return string */
    public static function replaceLast($search, $replace, $subject) {}
    /** @return string */
    public static function replaceEnd($search, $replace, $subject) {}
    /** @return ($subject */
    public static function replaceMatches($pattern, $replace, $subject, $limit = -1) {}
    /** @return string */
    public static function remove($search, $subject, $caseSensitive = true) {}
    /** @return string */
    public static function reverse(string $value) {}
    /** @return ($value */
    public static function start($value, $prefix) {}
    /** @return ($value */
    public static function upper($value) {}
    /** @return string */
    public static function title($value) {}
    /** @return string */
    public static function headline($value) {}
    /** @return string */
    public static function initials($value, $capitalize = false) {}
    /** @return string */
    public static function apa($value) {}
    /** @return string */
    public static function singular($value) {}
    /** @return string */
    public static function slug($title, $separator = '-', $language = 'en', $dictionary = null) {}
    /** @return string */
    public static function snake($value, $delimiter = '_') {}
    /** @return string */
    public static function trim($value, $charlist = NULL) {}
    /** @return string */
    public static function ltrim($value, $charlist = NULL) {}
    /** @return string */
    public static function rtrim($value, $charlist = NULL) {}
    /** @return string */
    public static function squish($value) {}
    /** @return ($needles */
    public static function startsWith($haystack, $needles) {}
    /** @return ($needles */
    public static function doesntStartWith($haystack, $needles) {}
    /** @return ($value */
    public static function studly($value) {}
    /** @return ($value */
    public static function pascal($value) {}
    /** @return string */
    public static function substr($string, $start, $length = NULL, $encoding = 'UTF-8') {}
    /** @return int */
    public static function substrCount($haystack, $needle, $offset = 0, $length = NULL) {}
    /** @return string|string[] */
    public static function substrReplace($string, $replace, $offset = 0, $length = NULL) {}
    /** @return string */
    public static function swap(array $map, $subject) {}
    public static function take($string, int $limit): string {}
    public static function toBase64($string): string {}
    /** @return ($strict */
    public static function fromBase64($string, $strict = false) {}
    /** @return ($string */
    public static function lcfirst($string) {}
    /** @return ($string */
    public static function ucfirst($string) {}
    /** @return ($string */
    public static function ucwords($string, $separators = ' 	
') {}
    /** @return ($string */
    public static function ucsplit($string) {}
    /** @return non-negative-int */
    public static function wordCount($string, $characters = NULL) {}
    /** @return string */
    public static function wordWrap($string, $characters = 75, $break = '
', $cutLongWords = false) {}
    /** @return \Ramsey\Uuid\UuidInterface */
    public static function uuid() {}
    /** @return \Ramsey\Uuid\UuidInterface */
    public static function uuid7($time = NULL) {}
    /** @return \Ramsey\Uuid\UuidInterface */
    public static function orderedUuid() {}
    /** @return void */
    public static function createUuidsUsing(?callable $factory = NULL) {}
    /** @return void */
    public static function createUuidsUsingSequence(array $sequence, $whenMissing = NULL) {}
    /** @return \Ramsey\Uuid\UuidInterface */
    public static function freezeUuids(?\Closure $callback = NULL) {}
    /** @return void */
    public static function createUuidsNormally() {}
    /** @return \Symfony\Component\Uid\Ulid */
    public static function ulid($time = NULL) {}
    /** @return void */
    public static function createUlidsNormally() {}
    /** @return void */
    public static function createUlidsUsing(?callable $factory = NULL) {}
    /** @return void */
    public static function createUlidsUsingSequence(array $sequence, $whenMissing = NULL) {}
    /** @return Ulid */
    public static function freezeUlids(?\Closure $callback = NULL) {}
    /** @return void */
    public static function flushCache() {}
}
