<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use JesseGall\CodeCommandments\Support\Pipes\Php\FindImplicitDataFrom;
use JesseGall\CodeCommandments\Support\Pipes\Php\ParsePhpAst;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpPipeline;
use JesseGall\PhpTypes\T_String;

/**
 * A codebase-wide census of object (non-array) `::from()` call sites, keyed by
 * the Data class's short name.
 *
 * The FromArrayOnly trait (and the `from([])`→`make()` rewrite) is only valid
 * for a Data class whose every `::from()` site is an array. The call sites,
 * though, usually live in OTHER files than the class — so a per-file check
 * (#49's `dependsOnMagic`) can't see them, and the trait/rewrite would be added
 * for a class that still has an object `::from()`, firing the runtime assert
 * (#64) or calling an undefined `make()` (#65). This scans the whole index once
 * and answers "does class X have an object `::from()` site anywhere?".
 */
final class DataFromSiteCensus
{
    /** @var array<string, array{object: array<string, true>, array: array<string, true>}> */
    private static array $cache = [];

    /**
     * Short class names with at least one object (non-array) `::from()` site.
     *
     * @param  list<string>  $suffixes  Data-class name suffixes to recognise
     * @return array<string, true>
     */
    public static function objectFromShortNames(CodebaseIndex $index, array $suffixes): array
    {
        return self::scan($index, $suffixes)['object'];
    }

    /**
     * Short class names with at least one PROVABLE-array `::from()` site (an array
     * literal / typed-array param / toArray()) — the positive proof #80 requires
     * before the trait may be added. A class with NO visible `::from()` site (a
     * framework/view-hydrated class) is absent here and so stays trait-less.
     *
     * @param  list<string>  $suffixes
     * @return array<string, true>
     */
    public static function arrayProvenShortNames(CodebaseIndex $index, array $suffixes): array
    {
        return self::scan($index, $suffixes)['array'];
    }

    /**
     * @param  list<string>  $suffixes
     * @return array{object: array<string, true>, array: array<string, true>}
     */
    private static function scan(CodebaseIndex $index, array $suffixes): array
    {
        $files = [];

        foreach ($index->classes() as $summary) {
            $files[$summary->filePath] = true;
        }

        $paths = array_keys($files);
        sort($paths);

        // Key by the file set (not spl_object_id, whose ids are reused after GC)
        // so the memo is stable and never served stale across runs/tests.
        $key = md5(implode('|', $paths) . '#' . implode(',', $suffixes));

        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $object = [];
        $array = [];
        $pipe = (new FindImplicitDataFrom)->withDataSuffixes($suffixes)->inCensusMode();

        foreach ($paths as $file) {
            $content = @file_get_contents($file);

            if ($content === false) {
                continue;
            }

            $context = PhpPipeline::make($file, $content)
                ->pipe(ParsePhpAst::class)
                ->pipe($pipe)
                ->getContext();

            foreach ($context->matches as $match) {
                $kind = $match->groups['kind'] ?? T_String::empty();
                $target = $match->groups['target'] ?? T_String::empty();

                if ($kind === 'nonarray') {
                    $object[$target] = true;
                } elseif (in_array($kind, ['array_from', 'empty_from', 'toarray_outside'], true)) {
                    $array[$target] = true;
                }
            }
        }

        return self::$cache[$key] = ['object' => $object, 'array' => $array];
    }
}
