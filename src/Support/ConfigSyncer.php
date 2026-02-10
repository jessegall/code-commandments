<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

/**
 * Syncs newly available prophets into an existing commandments.php config file.
 *
 * Discovers all prophets shipped with the package, compares them against
 * those already registered in each scroll, and inserts missing ones
 * into the config file source.
 */
class ConfigSyncer
{
    /**
     * Sync new prophets into a config file.
     *
     * @return array{added: array<array{class: string, scroll: string}>, source: string}
     */
    public function sync(string $configPath): array
    {
        $config = ConfigLoader::load($configPath);
        $source = file_get_contents($configPath);

        if ($source === false) {
            return ['added' => [], 'source' => ''];
        }

        $added = [];

        foreach ($config['scrolls'] ?? [] as $scrollName => $scrollConfig) {
            $type = $this->determineScrollType($scrollConfig);

            if ($type === null) {
                continue;
            }

            $available = $this->discoverProphets($type);
            $existing = $this->getExistingProphetClasses($scrollConfig['prophets'] ?? []);
            $new = array_diff_key($available, array_flip($existing));

            if (empty($new)) {
                continue;
            }

            $source = $this->insertProphetsIntoSource($source, $scrollName, $new);

            foreach (array_keys($new) as $class) {
                $added[] = ['class' => $class, 'scroll' => $scrollName];
            }
        }

        return ['added' => $added, 'source' => $source];
    }

    /**
     * Determine the prophet type (Backend/Frontend) from scroll extensions.
     */
    private function determineScrollType(array $scrollConfig): ?string
    {
        $extensions = $scrollConfig['extensions'] ?? [];

        if (in_array('php', $extensions, true)) {
            return 'Backend';
        }

        if (! empty(array_intersect(['vue', 'ts', 'js', 'tsx', 'jsx'], $extensions))) {
            return 'Frontend';
        }

        return null;
    }

    /**
     * Get the class names of prophets already registered in a scroll.
     *
     * @return array<string>
     */
    private function getExistingProphetClasses(array $prophets): array
    {
        $classes = [];

        foreach ($prophets as $key => $value) {
            $classes[] = is_string($key) ? $key : $value;
        }

        return $classes;
    }

    /**
     * Discover all prophets of a given type from the package source.
     *
     * @return array<string, array<string, string>> class => [config_key => default, ...]
     */
    private function discoverProphets(string $type): array
    {
        $dir = __DIR__ . '/../Prophets/' . $type;

        if (! is_dir($dir)) {
            return [];
        }

        $prophets = [];
        $files = scandir($dir);

        if ($files === false) {
            return [];
        }

        foreach ($files as $file) {
            if (! str_ends_with($file, 'Prophet.php')) {
                continue;
            }

            $className = 'JesseGall\\CodeCommandments\\Prophets\\' . $type . '\\' . str_replace('.php', '', $file);
            $configOptions = $this->extractConfigOptions($dir . '/' . $file);
            $prophets[$className] = $configOptions;
        }

        ksort($prophets);

        return $prophets;
    }

    /**
     * Extract config keys and defaults from a prophet source file.
     *
     * @return array<string, string> key => default value as raw PHP string
     */
    private function extractConfigOptions(string $filePath): array
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
        $constName = preg_replace('/^(self|static)::/', '', $constant);

        if (preg_match("/const\s+{$constName}\s*=\s*(.+?);/", $fileContent, $match)) {
            return trim($match[1]);
        }

        return null;
    }

    /**
     * Insert new prophet entries into the config file source.
     *
     * @param array<string, array<string, string>> $newProphets
     */
    private function insertProphetsIntoSource(string $source, string $scrollName, array $newProphets): string
    {
        $scrollPattern = "/'" . preg_quote($scrollName, '/') . "'\s*=>/";

        if (! preg_match($scrollPattern, $source, $m, PREG_OFFSET_CAPTURE)) {
            return $source;
        }

        $scrollPos = $m[0][1];

        $prophetsPos = strpos($source, "'prophets'", $scrollPos);

        if ($prophetsPos === false) {
            return $source;
        }

        $openBracket = strpos($source, '[', $prophetsPos);

        if ($openBracket === false) {
            return $source;
        }

        // Detect indentation from the 'prophets' line
        $lineStart = strrpos(substr($source, 0, $prophetsPos), "\n");
        $prophetsIndent = $prophetsPos - ($lineStart === false ? 0 : $lineStart + 1);
        $entryIndent = str_repeat(' ', $prophetsIndent + 4);
        $closingIndent = str_repeat(' ', $prophetsIndent);

        $closingBracket = $this->findMatchingBracket($source, $openBracket);

        if ($closingBracket === null) {
            return $source;
        }

        $between = trim(substr($source, $openBracket + 1, $closingBracket - $openBracket - 1));
        $entries = $this->renderProphetEntries($newProphets, $entryIndent);

        if ($between === '') {
            // Empty array: expand [] into multi-line
            $replacement = "[\n" . $entries . $closingIndent . ']';

            return substr($source, 0, $openBracket) . $replacement . substr($source, $closingBracket + 1);
        }

        // Non-empty: insert before the closing bracket's line
        $lineStart = strrpos(substr($source, 0, $closingBracket), "\n");

        if ($lineStart === false) {
            return $source;
        }

        $insertPos = $lineStart + 1;

        return substr($source, 0, $insertPos) . $entries . substr($source, $insertPos);
    }

    /**
     * Find the position of the ] that matches the [ at $openPos.
     */
    private function findMatchingBracket(string $source, int $openPos): ?int
    {
        $depth = 1;
        $pos = $openPos + 1;
        $len = strlen($source);
        $inString = false;
        $stringChar = '';

        while ($pos < $len && $depth > 0) {
            $char = $source[$pos];

            if ($inString) {
                if ($char === '\\') {
                    $pos++; // skip escaped character
                } elseif ($char === $stringChar) {
                    $inString = false;
                }
            } else {
                if ($char === "'" || $char === '"') {
                    $inString = true;
                    $stringChar = $char;
                } elseif ($char === '[') {
                    $depth++;
                } elseif ($char === ']') {
                    $depth--;

                    if ($depth === 0) {
                        return $pos;
                    }
                }
            }

            $pos++;
        }

        return null;
    }

    /**
     * Render prophet entries as config source lines.
     *
     * @param array<string, array<string, string>> $prophets
     */
    private function renderProphetEntries(array $prophets, string $indent): string
    {
        $lines = '';

        foreach ($prophets as $class => $configOptions) {
            if (empty($configOptions)) {
                $lines .= "{$indent}\\{$class}::class,\n";
            } else {
                $lines .= "{$indent}\\{$class}::class => [\n";

                foreach ($configOptions as $key => $default) {
                    $lines .= "{$indent}    // '{$key}' => {$default},\n";
                }

                $lines .= "{$indent}],\n";
            }
        }

        return $lines;
    }
}
