<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use JesseGall\PhpTypes\T_Json;
use JesseGall\PhpTypes\T_String;

class ConfigGenerator
{
    /**
     * Generate a commandments.php config file from detected projects.
     *
     * @param DetectedProject[] $projects
     */
    public function generate(array $projects, string $basePath): string
    {
        $singleProject = count($projects) === 1 && $projects[0]->path === $basePath;
        $scrolls = [];

        foreach ($projects as $project) {
            $prefix = $singleProject ? T_String::empty() : $project->name . '-';

            if ($project->hasPhp) {
                $scrollName = $singleProject ? 'backend' : $prefix . 'backend';
                $relativePath = $this->relativePath($basePath, $project->path . '/' . $project->phpSourcePath);

                $scrolls[$scrollName] = [
                    'path' => $relativePath,
                    'extensions' => ['php'],
                    'exclude' => [],
                    'prophets' => $this->discoverProphets('Backend'),
                ];
            }

            if ($project->hasFrontend) {
                $scrollName = $singleProject ? 'frontend' : $prefix . 'frontend';
                $relativePath = $this->relativePath($basePath, $project->path . '/' . $project->frontendSourcePath);

                $scrolls[$scrollName] = [
                    'path' => $relativePath,
                    'extensions' => ['vue', 'ts', 'js', 'tsx', 'jsx'],
                    'exclude' => ['node_modules', 'dist'],
                    'prophets' => $this->discoverProphets('Frontend'),
                ];
            }
        }

        return $this->render($scrolls);
    }

    /**
     * @return array<string, array<string, string>> class => [key => default, ...]
     */
    private function discoverProphets(string $type): array
    {
        $dir = __DIR__ . '/../Prophets/' . $type;

        if (!is_dir($dir)) {
            return [];
        }

        $prophets = [];
        $files = scandir($dir);

        if ($files === false) {
            return [];
        }

        foreach ($files as $file) {
            if (!str_ends_with($file, 'Prophet.php')) {
                continue;
            }

            $className = 'JesseGall\\CodeCommandments\\Prophets\\' . $type . '\\' . str_replace('.php', T_String::empty(), $file);
            $configOptions = (new ProphetConfigExtractor())->optionsFor($dir . '/' . $file);
            $prophets[$className] = $configOptions;
        }

        ksort($prophets);

        return $prophets;
    }

    /**
     * @param array<string, array{path: string, extensions: string[], exclude: string[], prophets: array<string, array<string, string>>}> $scrolls
     */
    private function render(array $scrolls): string
    {
        $lines = [];
        $lines[] = '<?php';
        $lines[] = T_String::empty();
        $lines[] = 'return [';
        $lines[] = "    'scrolls' => [";

        $scrollEntries = [];

        foreach ($scrolls as $name => $config) {
            $entry = [];
            $entry[] = "        '{$name}' => [";
            $entry[] = "            'path' => {$config['path']},";
            $entry[] = "            'extensions' => " . $this->renderArray($config['extensions']) . ',';
            $entry[] = "            'exclude' => " . $this->renderArray($config['exclude']) . ',';
            $entry[] = "            'prophets' => [";

            foreach ($config['prophets'] as $prophet => $configOptions) {
                if (empty($configOptions)) {
                    $entry[] = "                \\{$prophet}::class,";
                } else {
                    $entry[] = "                \\{$prophet}::class => [";

                    foreach ($configOptions as $key => $default) {
                        $entry[] = "                    // '{$key}' => {$default},";
                    }

                    $entry[] = '                ],';
                }
            }

            $entry[] = '            ],';
            $entry[] = '        ],';

            $scrollEntries[] = implode(T_String::NEWLINE, $entry);
        }

        $lines[] = implode(T_String::PARAGRAPH, $scrollEntries);
        $lines[] = '    ],';
        $lines[] = T_String::empty();
        $lines[] = "    'confession' => [";
        $lines[] = "        'tablet_path' => __DIR__ . '/.commandments/confessions.json',";
        $lines[] = '    ],';
        $lines[] = '];';
        $lines[] = T_String::empty();

        return implode(T_String::NEWLINE, $lines);
    }

    /**
     * @param string[] $items
     */
    private function renderArray(array $items): string
    {
        if (empty($items)) {
            return T_Json::emptyArray();
        }

        $quoted = array_map(fn (string $item) => "'{$item}'", $items);

        return '[' . implode(', ', $quoted) . ']';
    }

    private function relativePath(string $basePath, string $absolutePath): string
    {
        $basePath = rtrim($basePath, '/');
        $absolutePath = rtrim($absolutePath, '/');

        if ($absolutePath === $basePath || $absolutePath === $basePath . '/.') {
            return "__DIR__";
        }

        $relative = str_replace($basePath . '/', T_String::empty(), $absolutePath);

        return "__DIR__ . '/{$relative}'";
    }
}
