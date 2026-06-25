<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use JesseGall\PhpTypes\T_String;

/**
 * Reads a prophet source file's `$this->config('key', default)` calls into the
 * key => default-expression map used to seed a consumer's config entry.
 */
final class ProphetConfigExtractor
{
    /**
     * @return array<string, string>
     */
    public function optionsFor(string $filePath): array
    {
        $content = file_get_contents($filePath);

        if ($content === false) {
            return [];
        }

        $options = [];

        if (preg_match_all('/\$this->config\(\s*\'([^\']+)\'\s*,\s*(.+?)\)/', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $key = $match[1];

                if ($key === 'exclude') {
                    continue;
                }

                $default = trim($match[2]);

                // Resolve self::/static:: constant references from the file.
                if (str_contains($default, '::')) {
                    $resolved = $this->resolveConstant($content, $default);

                    if ($resolved !== null) {
                        $default = $resolved;
                    }
                }

                $options[$key] = $default;
            }
        }

        return $options;
    }

    private function resolveConstant(string $fileContent, string $constant): ?string
    {
        $constName = preg_replace('/^(self|static)::/', T_String::empty(), $constant);

        if (preg_match("/const\s+{$constName}\s*=\s*(.+?);/", $fileContent, $match)) {
            return trim($match[1]);
        }

        return null;
    }
}
