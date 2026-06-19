<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| README auto-generator
|------------------------------------------------------------------------------
|
| Regenerates the drift-prone sections of README.md from the SOURCE OF TRUTH —
| the prophet classes (their description()/auto-fixability) and the console
| command definitions (name/description/options). Everything between the
| AUTOGEN markers is replaced; prose outside them (prelude, install guide,
| custom-prophet guide, …) is left untouched.
|
| Run it with `composer readme`; `composer readme:check` fails when the README
| is stale (used by the pre-commit hook so it is always current).
|
*/

use JesseGall\CodeCommandments\Console\Application;
use JesseGall\CodeCommandments\Contracts\ParameterizedRepenter;
use JesseGall\CodeCommandments\Contracts\SinRepenter;
use Symfony\Component\Console\Input\InputOption;

require __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
$readmePath = $root . '/README.md';
$check = in_array('--check', $argv, true);

/**
 * Build the markdown for every built-in prophet, grouped by scroll.
 */
function prophetsSection(string $root): string
{
    $out = [];

    foreach (['Backend' => 'Backend (PHP)', 'Frontend' => 'Frontend (Vue / TypeScript)'] as $dir => $heading) {
        $rows = [];

        foreach (glob($root . "/src/Prophets/{$dir}/*.php") ?: [] as $file) {
            $short = basename($file, '.php');
            $fqcn = "JesseGall\\CodeCommandments\\Prophets\\{$dir}\\{$short}";

            if (! class_exists($fqcn)) {
                continue;
            }

            $reflection = new ReflectionClass($fqcn);

            if ($reflection->isAbstract()) {
                continue;
            }

            try {
                $prophet = $reflection->newInstance();
            } catch (Throwable) {
                continue;
            }

            $autoFix = ($prophet instanceof SinRepenter || $prophet instanceof ParameterizedRepenter) ? 'Yes' : '—';
            $rows[$short] = [$short, $autoFix, escapeCell($prophet->description())];
        }

        ksort($rows);

        $out[] = "### {$heading}";
        $out[] = '';
        $out[] = sprintf('_%d prophets._', count($rows));
        $out[] = '';
        $out[] = '| Prophet | Auto-fix | What it enforces |';
        $out[] = '|---|---|---|';

        foreach ($rows as [$name, $autoFix, $desc]) {
            $out[] = "| `{$name}` | {$autoFix} | {$desc} |";
        }

        $out[] = '';
    }

    return rtrim(implode("\n", $out));
}

/**
 * Build the markdown for every console command + its options.
 */
function commandsSection(): string
{
    $app = new Application();
    $out = [];

    $commands = [];

    foreach ($app->all() as $command) {
        if ($command->isHidden() || in_array($command->getName(), ['help', 'list', 'completion', '_complete'], true)) {
            continue;
        }

        $commands[$command->getName()] = $command;
    }

    ksort($commands);

    $out[] = '| Command | Purpose |';
    $out[] = '|---|---|';

    foreach ($commands as $name => $command) {
        $out[] = sprintf('| [`%s`](#%s) | %s |', $name, anchor($name), escapeCell($command->getDescription()));
    }

    $out[] = '';

    foreach ($commands as $name => $command) {
        $out[] = "### `{$name}`";
        $out[] = '';
        $out[] = escapeCell($command->getDescription()) . '.';
        $out[] = '';

        $options = array_values(array_filter(
            $command->getDefinition()->getOptions(),
            static fn (InputOption $o): bool => ! in_array($o->getName(), ['help', 'quiet', 'verbose', 'version', 'ansi', 'no-ansi', 'no-interaction'], true),
        ));

        if ($options === []) {
            continue;
        }

        $out[] = '| Flag | Argument | Description |';
        $out[] = '|---|---|---|';

        foreach ($options as $option) {
            $flag = '`--' . $option->getName() . '`';

            if ($option->getShortcut() !== null) {
                $flag .= ', `-' . $option->getShortcut() . '`';
            }

            $arg = $option->acceptValue() ? ($option->isValueOptional() ? '`[value]`' : '`<value>`') : '—';
            $out[] = sprintf('| %s | %s | %s |', $flag, $arg, escapeCell($option->getDescription()));
        }

        $out[] = '';
    }

    return rtrim(implode("\n", $out));
}

function escapeCell(string $text): string
{
    // Escape `|` (column separator), newlines, AND angle brackets — a literal
    // `<script setup>` / `<template v-for>` in a description is otherwise parsed
    // as a real HTML tag by the markdown renderer, swallowing the rest of the
    // table.
    return str_replace(['|', "\n", '<', '>'], ['\\|', ' ', '&lt;', '&gt;'], trim($text));
}

function anchor(string $name): string
{
    return strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
}

/**
 * Replace the content between `<!-- AUTOGEN:KEY:START -->` and the matching END
 * marker. Fails loudly if the markers are missing.
 */
function replaceRegion(string $content, string $key, string $body): string
{
    $start = "<!-- AUTOGEN:{$key}:START -->";
    $end = "<!-- AUTOGEN:{$key}:END -->";
    $pattern = '/' . preg_quote($start, '/') . '.*?' . preg_quote($end, '/') . '/s';

    if (preg_match($pattern, $content) !== 1) {
        fwrite(STDERR, "Missing AUTOGEN markers for {$key} in README.md\n");
        exit(2);
    }

    return preg_replace($pattern, $start . "\n\n" . $body . "\n\n" . $end, $content);
}

$readme = file_get_contents($readmePath);
$updated = replaceRegion($readme, 'COMMANDS', commandsSection());
$updated = replaceRegion($updated, 'PROPHETS', prophetsSection($root));

if ($check) {
    if ($updated !== $readme) {
        fwrite(STDERR, "README.md is out of date — run `composer readme`.\n");
        exit(1);
    }

    echo "README.md is up to date.\n";
    exit(0);
}

file_put_contents($readmePath, $updated);
echo "README.md regenerated.\n";
