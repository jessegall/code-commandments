<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins;

/**
 * One generatable file a sin's fix needs — the reusable, package-providable construct its
 * {@see Sin::suggestion} names (a no-op invokable, a `Registry` base). The code lives in a
 * real `stubs/<name>.stub` file (not inline), with a `{namespace}` placeholder; the
 * `scaffold` command writes it into the consumer's source root with their namespace
 * injected. The construct it creates is exactly the one the suggestion names, so advice
 * and generated code never drift.
 */
final class Scaffold
{
    private const string STUBS = __DIR__ . '/../../stubs';

    /**
     * @param  string  $path  the target sub-path under the consumer's source root, e.g. `Support/Invokable.php`
     * @param  string  $stub  the stub file under `stubs/`, e.g. `Invokable.php.stub`
     */
    public function __construct(
        public readonly string $path,
        public readonly string $stub,
    ) {}

    /**
     * The stub's code with the consumer's namespace injected (`{namespace}` → `App\Support`).
     */
    public function render(string $namespace): string
    {
        return str_replace('{namespace}', $namespace, (string) file_get_contents(self::STUBS . '/' . $this->stub));
    }

    /**
     * The class/interface name this scaffolds (the target file basename).
     */
    public function class(): string
    {
        return basename($this->path, '.php');
    }
}
