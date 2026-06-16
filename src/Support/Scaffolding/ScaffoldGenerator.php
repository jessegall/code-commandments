<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Scaffolding;

/**
 * Stamps the package's support-class stubs into the consumer's namespace,
 * rewriting the namespace to the configured one. Idempotent: an existing
 * file is never overwritten unless $force is set.
 */
final class ScaffoldGenerator
{
    public const STATUS_CREATED = 'created';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_REWRITTEN = 'rewritten';
    public const STATUS_MISSING_STUB = 'missing_stub';

    public function __construct(
        private readonly string $stubDir,
    ) {}

    public static function packaged(): self
    {
        return new self(__DIR__ . '/../../../stubs/scaffold');
    }

    /**
     * @param  list<string>  $except  scaffold names to skip
     * @return list<array{name: string, class: string, status: string}>
     */
    public function generate(string $namespace, string $path, bool $force = false, array $except = []): array
    {
        $namespace = trim($namespace, '\\');
        $results = [];

        foreach (ScaffoldRegistry::all() as $scaffold) {
            if (in_array($scaffold->name, $except, true)) {
                continue;
            }

            $results[] = [
                'name' => $scaffold->name,
                'class' => $namespace . '\\' . $scaffold->className,
                'status' => $this->write($scaffold, $namespace, $path, $force),
            ];
        }

        return $results;
    }

    private function write(Scaffold $scaffold, string $namespace, string $path, bool $force): string
    {
        $stubPath = $this->stubDir . '/' . $scaffold->stubFile;

        if (! is_file($stubPath)) {
            return self::STATUS_MISSING_STUB;
        }

        $target = rtrim($path, '/') . '/' . $scaffold->className . '.php';
        $exists = is_file($target);

        if ($exists && ! $force) {
            return self::STATUS_SKIPPED;
        }

        $contents = str_replace('{{ namespace }}', $namespace, (string) file_get_contents($stubPath));

        if (! is_dir($path)) {
            @mkdir($path, 0755, true);
        }

        if (@file_put_contents($target, $contents) === false) {
            return self::STATUS_SKIPPED;
        }

        return $exists ? self::STATUS_REWRITTEN : self::STATUS_CREATED;
    }
}
