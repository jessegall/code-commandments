<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use Closure;

/**
 * A tool this package carries as source and builds on first use into the user's cache — one folder per
 * version of its sources, so a change to any of them builds a new one, and one build at a time, so two runs
 * never build over each other.
 */
final readonly class BuiltTool
{
    /**
     * @param  list<string>  $sources  the files whose contents make this version of the tool
     * @param  string  $marker  the file a finished build leaves in its folder
     */
    public function __construct(
        private string $name,
        private array $sources,
        private string $marker,
    ) {}

    /**
     * Where this version of the tool is built.
     */
    public function folder(): string
    {
        return self::cache() . "/{$this->name}/" . $this->fingerprint();
    }

    /**
     * Is this version built — by $build, handed the folder, when it was not yet? False when the build
     * fails or its lock cannot be taken.
     *
     * @param  Closure(string): bool  $build
     */
    public function isBuiltBy(Closure $build): bool
    {
        $folder = $this->folder();
        $marker = "{$folder}/{$this->marker}";

        if (is_file($marker)) {
            return true;
        }

        return Lock::on("{$folder}.lock")->isSomeAnd(static function (Lock $lock) use ($build, $folder, $marker): bool {
            $done = is_file($marker) || ($build($folder) && is_file($marker));
            $lock->release();

            return $done;
        });
    }

    /**
     * The user's cache for built tools.
     */
    private static function cache(): string
    {
        $cache = getenv('XDG_CACHE_HOME');

        if ($cache !== false && $cache !== '') {
            return "{$cache}/code-commandments";
        }

        return (getenv('HOME') ?: sys_get_temp_dir()) . '/.cache/code-commandments';
    }

    private function fingerprint(): string
    {
        $sources = $this->sources;
        sort($sources);

        return substr(sha1(implode("\n", array_map(static fn (string $file): string => sha1_file($file) ?: '', $sources))), 0, 16);
    }
}
