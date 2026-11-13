<?php

declare(strict_types=1);

namespace CodeCommandments\PhpBridge;


/**
 * What one stream is asked for: the paths to parse, the ones judged, and how the stream names them.
 */
final readonly class Request
{
    /**
     * Each path of $write, as a set to look a file and its folders up in.
     *
     * @var array<string, true>
     */
    private array $judged;

    /**
     * @param  list<string>  $paths
     * @param  list<string>  $write
     * @param  array<string, string>  $contents  absolute path => the text to read in place of the disk's
     */
    public function __construct(
        public array $paths,
        public array $write,
        public ?string $autoload,
        public bool $serve,
        public array $contents = [],
    ) {
        $this->judged = array_fill_keys(array_map(static fn (string $path): string => rtrim($path, '/'), $write), true);
    }

    /**
     * @param  list<string>  $arguments
     */
    public static function fromArguments(array $arguments): self
    {
        $paths = [];
        $write = [];
        $autoload = null;
        $serve = false;
        foreach ($arguments as $argument) {
            match (true) {
                $argument === '--serve' => $serve = true,
                str_starts_with($argument, '--write=') => $write[] = self::absolute(substr($argument, 8)),
                str_starts_with($argument, '--autoload=') => $autoload = self::absolute(substr($argument, 11)),
                str_starts_with($argument, '--') => throw BadRequest::forFlag($argument),
                default => $paths[] = self::absolute($argument),
            };
        }
        if ($paths === [] && ! $serve) {
            throw BadRequest::forNothingToRead();
        }

        return new self($paths, $write, $autoload, $serve);
    }

    /**
     * @param  array{paths?: list<string>, write?: list<string>, contents?: array<string, string>}  $request
     */
    public function answering(array $request): self
    {
        if (($request['paths'] ?? []) === []) {
            throw BadRequest::forNoPaths();
        }

        $contents = $request['contents'] ?? [];
        $drafted = static fn (string $path): string => array_key_exists($path, $contents) ? $path : self::absolute($path);

        return new self(
            array_map($drafted, $request['paths'] ?? []),
            array_map($drafted, $request['write'] ?? []),
            $this->autoload,
            false,
            $contents,
        );
    }

    /**
     * Whether the stream judges $file, or only reads it to resolve into.
     */
    public function judges(string $file): bool
    {
        if ($this->judged === []) {
            return true;
        }
        $held = $file;
        while (! isset($this->judged[$held])) {
            if (dirname($held) === $held) {
                return false;
            }
            $held = dirname($held);
        }

        return true;
    }

    private static function absolute(string $path): string
    {
        $folder = realpath(dirname($path));
        if ($folder === false || (! file_exists($path) && ! is_link($path))) {
            throw BadRequest::forMissingPath($path);
        }

        return $folder . '/' . basename($path);
    }
}
