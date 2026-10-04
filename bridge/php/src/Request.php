<?php

declare(strict_types=1);

namespace CodeCommandments\PhpBridge;


/**
 * What one stream is asked for: the paths to parse, the ones judged, and how the stream names them.
 */
final readonly class Request
{
    /**
     * @param  list<string>  $paths
     * @param  list<string>  $write
     * @param  array<string, string>  $renames
     * @param  array<string, string>  $contents  absolute path => the text to read in place of the disk's
     * @param  ?string  $cache  the folder each file's line is kept in between runs; none when null
     */
    public function __construct(
        public array $paths,
        public array $write,
        public ?string $autoload,
        public array $renames,
        public bool $serve,
        public array $contents = [],
        public ?string $cache = null,
    ) {}

    /**
     * @param  list<string>  $arguments
     */
    public static function fromArguments(array $arguments): self
    {
        $paths = [];
        $write = [];
        $autoload = null;
        $renames = [];
        $serve = false;
        $cache = null;
        foreach ($arguments as $argument) {
            match (true) {
                $argument === '--serve' => $serve = true,
                str_starts_with($argument, '--write=') => $write[] = self::absolute(substr($argument, 8)),
                str_starts_with($argument, '--autoload=') => $autoload = self::absolute(substr($argument, 11)),
                str_starts_with($argument, '--rename=') => $renames += self::rename(substr($argument, 9)),
                str_starts_with($argument, '--cache=') => $cache = substr($argument, 8),
                str_starts_with($argument, '--') => throw BadRequest::forFlag($argument),
                default => $paths[] = self::absolute($argument),
            };
        }
        if ($paths === [] && ! $serve) {
            throw BadRequest::forNothingToRead();
        }

        return new self($paths, $write, $autoload, $renames, $serve, cache: $cache);
    }

    /**
     * @param  array{paths?: list<string>, write?: list<string>, contents?: array<string, string>}  $request
     */
    public function answering(array $request): self
    {
        if (($request['paths'] ?? []) === []) {
            throw BadRequest::forNoPaths();
        }

        return new self(
            array_map(self::absolute(...), $request['paths'] ?? []),
            array_map(self::absolute(...), $request['write'] ?? []),
            $this->autoload,
            $this->renames,
            false,
            $request['contents'] ?? [],
            $this->cache,
        );
    }

    /**
     * Whether the stream judges $file, or only reads it to resolve into.
     */
    public function judges(string $file): bool
    {
        if ($this->write === []) {
            return true;
        }
        foreach ($this->write as $path) {
            if ($file === $path || str_starts_with($file, rtrim($path, '/') . '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * $path as the stream names it.
     */
    public function shown(string $path): string
    {
        foreach ($this->renames as $from => $to) {
            if (str_starts_with($path . '/', $from)) {
                return rtrim($to . substr($path . '/', strlen($from)), '/');
            }
        }

        return $path;
    }

    private static function absolute(string $path): string
    {
        $real = realpath($path);
        if ($real === false) {
            throw BadRequest::forMissingPath($path);
        }

        return $real;
    }

    /**
     * @return array<string, string>
     */
    private static function rename(string $pair): array
    {
        $parts = explode('=', $pair, 2);
        if (count($parts) !== 2) {
            throw BadRequest::forRename($pair);
        }

        return [self::absolute($parts[0]) . '/' => rtrim($parts[1], '/') . '/'];
    }
}
