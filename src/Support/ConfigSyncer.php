<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use ReflectionClass;
use ReflectionException;
use JesseGall\PhpTypes\T_String;

/**
 * Syncs newly available prophets into an existing commandments.php config file.
 *
 * Discovers all prophets shipped with the package, compares them against
 * those already registered in each scroll, and inserts missing ones
 * into the config file source.
 *
 * With `$after` set, only prophets marked `#[IntroducedIn(version)]`
 * with `version > $after` (semver) are added. Untagged prophets are
 * treated as predating the versioning scheme and skipped in filtered
 * mode — this prevents intentionally-removed prophets from being
 * re-added on every upgrade.
 */
class ConfigSyncer
{
    /**
     * Sync new prophets into a config file.
     *
     * @param  string|null  $after  When set, only add prophets whose
     *   `#[IntroducedIn(...)]` version is strictly greater than this.
     *   Pass null for the classic blanket-add behavior.
     * @return array{added: array<array{class: string, scroll: string, introduced_in: ?string}>, source: string}
     */
    public function sync(string $configPath, ?string $after = null): array
    {
        $config = ConfigLoader::load($configPath);
        $source = file_get_contents($configPath);

        if ($source === false) {
            return ['added' => [], 'source' => T_String::empty()];
        }

        $added = [];

        foreach ($config['scrolls'] ?? [] as $scrollName => $scrollConfig) {
            $type = $this->determineScrollType($scrollConfig);

            if ($type === null) {
                continue;
            }

            $available = $this->discoverProphets($type);
            $existing = $this->getExistingProphetClasses($scrollConfig['prophets'] ?? []);
            $missing = array_diff_key($available, array_flip($existing));

            if ($after !== null) {
                $missing = $this->filterByIntroducedAfter($missing, $after);
            }

            if (empty($missing)) {
                continue;
            }

            $newEntries = array_map(
                fn (array $meta) => $meta['config'],
                $missing,
            );

            $source = $this->insertProphetsIntoSource($source, $scrollName, $newEntries);

            foreach ($missing as $class => $meta) {
                $added[] = [
                    'class' => $class,
                    'scroll' => $scrollName,
                    'introduced_in' => $meta['introduced_in'] ?? null,
                ];
            }
        }

        return ['added' => $added, 'source' => $source];
    }

    /**
     * Keep only prophets whose `introduced_in` version is strictly greater
     * than `$after`. Untagged prophets are skipped in filtered mode.
     *
     * @param  array<string, array{config: array<string, string>, introduced_in: ?string}>  $prophets
     * @return array<string, array{config: array<string, string>, introduced_in: ?string}>
     */
    private function filterByIntroducedAfter(array $prophets, string $after): array
    {
        $out = [];

        foreach ($prophets as $class => $meta) {
            $introducedIn = $meta['introduced_in'] ?? null;

            if ($introducedIn === null) {
                continue;
            }

            if (version_compare($introducedIn, $after, '>')) {
                $out[$class] = $meta;
            }
        }

        return $out;
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
     * @return array<string, array{config: array<string, string>, introduced_in: ?string}>
     */
    private function discoverProphets(string $type): array
    {
        $prophets = [];

        foreach (ProphetFiles::each($type) as [$className, $filePath]) {
            $prophets[$className] = [
                'config' => (new ProphetConfigExtractor())->optionsFor($filePath),
                'introduced_in' => $this->extractIntroducedIn($className),
            ];
        }

        ksort($prophets);

        return $prophets;
    }

    /**
     * Read the `#[IntroducedIn(...)]` attribute from a prophet class via
     * reflection. Returns null when the attribute is absent.
     */
    private function extractIntroducedIn(string $className): ?string
    {
        try {
            $ref = new ReflectionClass($className);
        } catch (ReflectionException) {
            return null;
        }

        $attributes = $ref->getAttributes(IntroducedIn::class);

        if (empty($attributes)) {
            return null;
        }

        return $attributes[0]->newInstance()->version;
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
        $lineStart = strrpos(substr($source, 0, $prophetsPos), T_String::NEWLINE);
        $prophetsIndent = $prophetsPos - ($lineStart === false ? 0 : $lineStart + 1);
        $entryIndent = str_repeat(' ', $prophetsIndent + 4);
        $closingIndent = str_repeat(' ', $prophetsIndent);

        $closingBracket = $this->findMatchingBracket($source, $openBracket);

        if ($closingBracket === null) {
            return $source;
        }

        $between = trim(substr($source, $openBracket + 1, $closingBracket - $openBracket - 1));
        $entries = $this->renderProphetEntries($newProphets, $entryIndent);

        if (T_String::isEmpty($between)) {
            // Empty array: expand [] into multi-line
            $replacement = "[\n" . $entries . $closingIndent . ']';

            return substr($source, 0, $openBracket) . $replacement . substr($source, $closingBracket + 1);
        }

        // Non-empty: insert before the closing bracket's line
        $lineStart = strrpos(substr($source, 0, $closingBracket), T_String::NEWLINE);

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
        $stringChar = T_String::empty();

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
        $lines = T_String::empty();

        foreach ($prophets as $class => $configOptions) {
            if (empty($configOptions)) {
                $lines .= sprintf(
                    '%s\\%s::class,%s',
                    $indent,
                    $class,
                    T_String::NEWLINE,
                );
            } else {
                $lines .= sprintf(
                    '%s\\%s::class => [%s',
                    $indent,
                    $class,
                    T_String::NEWLINE,
                );

                foreach ($configOptions as $key => $default) {
                    $lines .= sprintf(
                        '%s    // \'%s\' => %s,%s',
                        $indent,
                        $key,
                        $default,
                        T_String::NEWLINE,
                    );
                }

                $lines .= sprintf(
                    '%s],%s',
                    $indent,
                    T_String::NEWLINE,
                );
            }
        }

        return $lines;
    }
}
