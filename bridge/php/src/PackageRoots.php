<?php

declare(strict_types=1);

namespace CodeCommandments\PhpBridge;

/**
 * The PSR-4 roots a project's composer.json autoloads, each a folder and the namespace it holds: the package a file
 * belongs to is the root namespace of the deepest root folder holding it, the unit the project ships together.
 */
final readonly class PackageRoots
{
    /**
     * @param  array<string, string>  $namespaces  each root folder, absolute, and the namespace it autoloads
     */
    private function __construct(private ?string $manifest, private array $namespaces) {}

    public static function nearest(?string $folder): self
    {
        if ($folder === null) {
            return new self(null, []);
        }
        $manifest = "{$folder}/composer.json";
        $declared = json_decode((string) file_get_contents($manifest), true);
        $namespaces = [];
        foreach (['autoload', 'autoload-dev'] as $section) {
            foreach ($declared[$section]['psr-4'] ?? [] as $namespace => $roots) {
                foreach ((array) $roots as $root) {
                    $real = realpath("{$folder}/{$root}");
                    if ($real !== false) {
                        $namespaces[$real] = rtrim((string) $namespace, '\\');
                    }
                }
            }
        }

        return new self(realpath($manifest) ?: $manifest, $namespaces);
    }

    public function packageOf(string $path): ?string
    {
        $deepest = null;
        foreach ($this->namespaces as $root => $namespace) {
            if (str_starts_with($path, $root.DIRECTORY_SEPARATOR) && ($deepest === null || strlen($root) > strlen($deepest))) {
                $deepest = $root;
            }
        }

        return $deepest === null ? null : $this->namespaces[$deepest];
    }

    /**
     * @return list<string>
     */
    public function reads(string $path): array
    {
        return $this->manifest === null || $this->packageOf($path) === null ? [] : [$this->manifest];
    }
}
