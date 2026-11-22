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
    public static function of($string) {}
    public static function after($subject, $search) {}
    public static function afterLast($subject, $search) {}
    public static function ascii($value, $language = 'en') {}
    public static function transliterate($string, $unknown = '?', $strict = false) {}
    public static function before($subject, $search) {}
    public static function beforeLast($subject, $search) {}
    public static function between($subject, $from, $to) {}
    public static function betweenFirst($subject, $from, $to) {}
    public static function camel($value) {}
    public static function charAt($subject, $index) {}
    public static function chopStart($subject, $needle) {}
    public static function chopEnd($subject, $needle) {}
    public static function contains($haystack, $needles, $ignoreCase = false) {}
    public static function containsAll($haystack, $needles, $ignoreCase = false) {}
    public static function doesntContain($haystack, $needles, $ignoreCase = false) {}
    public static function convertCase(string $string, int $mode = \Illuminate\Support\MB_CASE_FOLD, ?string $encoding = 'UTF-8') {}
    public static function deduplicate(string $string, array|string $characters = ' ') {}
    public static function endsWith($haystack, $needles) {}
    public static function doesntEndWith($haystack, $needles) {}
    public static function excerpt($text, $phrase = '', $options = array (
)) {}
    public static function finish($value, $cap) {}
    public static function wrap($value, $before, $after = NULL) {}
    public static function unwrap($value, $before, $after = NULL) {}
    public static function is($pattern, $value, $ignoreCase = false) {}
    public static function isAscii($value) {}
    public static function isJson($value) {}
    public static function isUrl($value, array $protocols = array (
)) {}
    public static function isUuid($value, $version = NULL) {}
    public static function isUlid($value) {}
    public static function kebab($value) {}
    public static function length($value, $encoding = NULL) {}
    public static function limit($value, $limit = 100, $end = '...', $preserveWords = false) {}
    public static function lower($value) {}
    public static function words($value, $words = 100, $end = '...') {}
    public static function markdown($string, array $options = array (
), array $extensions = array (
)) {}
    public static function inlineMarkdown($string, array $options = array (
), array $extensions = array (
)) {}
    public static function mask($string, $character, $index, $length = NULL, $encoding = 'UTF-8') {}
    public static function match($pattern, $subject) {}
    public static function isMatch($pattern, $value) {}
    public static function matchAll($pattern, $subject) {}
    public static function numbers($value) {}
    public static function padBoth($value, $length, $pad = ' ') {}
    public static function padLeft($value, $length, $pad = ' ') {}
    public static function padRight($value, $length, $pad = ' ') {}
    public static function parseCallback($callback, $default = NULL) {}
    public static function plural($value, $count = 2, $prependCount = false) {}
    public static function pluralStudly($value, $count = 2) {}
    public static function pluralPascal($value, $count = 2) {}
    public static function password($length = 32, $letters = true, $numbers = true, $symbols = true, $spaces = false) {}
    public static function position($haystack, $needle, $offset = 0, $encoding = NULL) {}
    public static function random($length = 16) {}
    public static function createRandomStringsUsing(?callable $factory = NULL) {}
    public static function createRandomStringsUsingSequence(array $sequence, $whenMissing = NULL) {}
    public static function createRandomStringsNormally() {}
    public static function repeat(string $string, int $times) {}
    public static function replaceArray($search, $replace, $subject) {}
    private static function toStringOr($value, $fallback) {}
    public static function replace($search, $replace, $subject, $caseSensitive = true) {}
    public static function replaceFirst($search, $replace, $subject) {}
    public static function replaceStart($search, $replace, $subject) {}
    public static function replaceLast($search, $replace, $subject) {}
    public static function replaceEnd($search, $replace, $subject) {}
    public static function replaceMatches($pattern, $replace, $subject, $limit = -1) {}
    public static function remove($search, $subject, $caseSensitive = true) {}
    public static function reverse(string $value) {}
    public static function start($value, $prefix) {}
    public static function upper($value) {}
    public static function title($value) {}
    public static function headline($value) {}
    public static function initials($value, $capitalize = false) {}
    public static function apa($value) {}
    public static function singular($value) {}
    public static function slug($title, $separator = '-', $language = 'en', $dictionary = null) {}
    public static function snake($value, $delimiter = '_') {}
    public static function trim($value, $charlist = NULL) {}
    public static function ltrim($value, $charlist = NULL) {}
    public static function rtrim($value, $charlist = NULL) {}
    public static function squish($value) {}
    public static function startsWith($haystack, $needles) {}
    public static function doesntStartWith($haystack, $needles) {}
    public static function studly($value) {}
    public static function pascal($value) {}
    public static function substr($string, $start, $length = NULL, $encoding = 'UTF-8') {}
    public static function substrCount($haystack, $needle, $offset = 0, $length = NULL) {}
    public static function substrReplace($string, $replace, $offset = 0, $length = NULL) {}
    public static function swap(array $map, $subject) {}
    public static function take($string, int $limit): string {}
    public static function toBase64($string): string {}
    public static function fromBase64($string, $strict = false) {}
    public static function lcfirst($string) {}
    public static function ucfirst($string) {}
    public static function ucwords($string, $separators = ' 	
') {}
    public static function ucsplit($string) {}
    public static function wordCount($string, $characters = NULL) {}
    public static function wordWrap($string, $characters = 75, $break = '
', $cutLongWords = false) {}
    public static function uuid() {}
    public static function uuid7($time = NULL) {}
    public static function orderedUuid() {}
    public static function createUuidsUsing(?callable $factory = NULL) {}
    public static function createUuidsUsingSequence(array $sequence, $whenMissing = NULL) {}
    public static function freezeUuids(?\Closure $callback = NULL) {}
    public static function createUuidsNormally() {}
    public static function ulid($time = NULL) {}
    public static function createUlidsNormally() {}
    public static function createUlidsUsing(?callable $factory = NULL) {}
    public static function createUlidsUsingSequence(array $sequence, $whenMissing = NULL) {}
    public static function freezeUlids(?\Closure $callback = NULL) {}
    public static function flushCache() {}
}
