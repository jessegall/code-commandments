<?php

declare(strict_types=1);

namespace CodeCommandments\PhpBridge;

/**
 * The .php files a request names, sorted, each once. The bridge walks no folder: the Go side lists every file through
 * its one funnel, so a folder named here is refused, and a link, which the funnel never hands on, is left out.
 */
final class Sources
{
    /**
     * @param  list<string>  $paths
     * @param  array<string, string>  $contents  a named file here that is not on disk is read from its text
     * @return list<string>
     */
    public static function in(array $paths, array $contents = []): array
    {
        $files = [];
        foreach ($paths as $path) {
            if (is_link($path)) {
                continue;
            }
            if (is_dir($path)) {
                throw BadRequest::forFolder($path);
            }
            if (str_ends_with($path, '.php') && (is_file($path) || array_key_exists($path, $contents))) {
                $files[$path] = true;
            }
        }
        $files = array_keys($files);
        sort($files);

        return $files;
    }
}
