<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use JesseGall\CodeCommandments\Results\Severity;

/**
 * Rewrites a legacy `ProphetClass::class => ['severity' => 'sin', ...]` prophets
 * list into the fluent, self-documenting form
 * `ProphetClass::make()->severity(Severity::Sin)->set(...)`. The `severity` key
 * maps to `->severity()` / `->disabled()`; every other setting maps to the generic
 * `->set()` escape hatch (swap in a prophet's typed wrapper — `->minCallers(10)` —
 * where one exists). Non-destructive: it produces source text to review and paste.
 */
final class ConfigMigrator
{
    private const PROPHET_NS = 'JesseGall\\CodeCommandments\\Prophets\\';

    /**
     * Rewrite the config SOURCE in place: replace each scroll's `'prophets' => [ … ]`
     * array (matched in scroll order) with the fluent form, and inject the needed
     * `use` statements. Everything else — `__DIR__`, excludes, comments, other keys —
     * is preserved verbatim, since only the prophet arrays are touched.
     *
     * @param  array<string, mixed>  $config  the loaded config (for scroll order + prophet lists)
     */
    public function rewriteSource(string $source, array $config): string
    {
        $scrolls = array_values($config['scrolls'] ?? []);
        $offset = 0;
        $index = 0;

        while (($pos = strpos($source, "'prophets' => [", $offset)) !== false) {
            if (! isset($scrolls[$index])) {
                break;
            }

            $prophets = is_array($scrolls[$index]['prophets'] ?? null) ? $scrolls[$index]['prophets'] : [];
            $index++;

            $open = $pos + strlen("'prophets' => ["); // first char INSIDE the bracket
            $close = $this->matchClosingBracket($source, $open - 1);

            if ($close === null) {
                $offset = $pos + 1;

                continue;
            }

            $replacement = "\n" . $this->migrateProphets($prophets) . "\n            ";
            $source = substr($source, 0, $open) . $replacement . substr($source, $close);
            $offset = $open + strlen($replacement);
        }

        return $this->injectUseStatements($source, $scrolls);
    }

    /**
     * The index of the `]` that closes the `[` at $openBracketPos, ignoring brackets
     * inside single/double-quoted strings.
     */
    private function matchClosingBracket(string $source, int $openBracketPos): ?int
    {
        $depth = 0;
        $length = strlen($source);
        $quote = null;

        for ($i = $openBracketPos; $i < $length; $i++) {
            $char = $source[$i];

            if ($quote !== null) {
                if ($char === '\\') {
                    $i++; // skip the escaped char
                } elseif ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === "'" || $char === '"') {
                $quote = $char;
            } elseif ($char === '[') {
                $depth++;
            } elseif ($char === ']') {
                $depth--;

                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    /**
     * Add `use …\Prophets\Backend;`, `…\Prophets\Frontend;` (when a frontend scroll
     * exists) and `…\Results\Severity;` after the last existing `use`, skipping any
     * already present.
     *
     * @param  list<array<string, mixed>>  $scrolls
     */
    private function injectUseStatements(string $source, array $scrolls): string
    {
        $needed = [
            'JesseGall\\CodeCommandments\\Prophets\\Backend',
            'JesseGall\\CodeCommandments\\Results\\Severity',
        ];

        foreach ($scrolls as $scroll) {
            foreach ($scroll['prophets'] ?? [] as $key => $value) {
                $class = is_string($key) ? $key : (string) $value;

                if (str_contains($class, '\\Frontend\\')) {
                    $needed[] = 'JesseGall\\CodeCommandments\\Prophets\\Frontend';

                    break 2;
                }
            }
        }

        $additions = '';

        foreach (array_unique($needed) as $use) {
            if (! preg_match('/^use\s+' . preg_quote($use, '/') . '\s*;/m', $source)) {
                $additions .= "use {$use};\n";
            }
        }

        if ($additions === '') {
            return $source;
        }

        // Insert after the last existing `use …;` line.
        if (preg_match_all('/^use\s+[^;]+;/m', $source, $matches, PREG_OFFSET_CAPTURE)) {
            $last = end($matches[0]);
            $insertAt = $last[1] + strlen($last[0]);

            return substr($source, 0, $insertAt) . "\n" . rtrim($additions) . substr($source, $insertAt);
        }

        return $source;
    }

    /**
     * Render a scroll's prophets list as a fluent `'prophets' => [ ... ]` block.
     *
     * @param  array<int|string, mixed>  $prophets  the legacy list (class, or class => config)
     */
    public function migrateProphets(array $prophets, string $indent = '            '): string
    {
        $lines = [];

        foreach ($prophets as $key => $value) {
            if (is_string($key)) {
                $class = $key;
                $config = is_array($value) ? $value : [];
            } else {
                $class = (string) $value;
                $config = [];
            }

            // A prophet retired/removed in the installed version no longer exists —
            // emitting `X::make()` would fatal on config load, so drop it with a note.
            if (! class_exists(ltrim($class, '\\'))) {
                $lines[] = $indent . '// removed (no longer in the package): ' . $this->classReference($class);

                continue;
            }

            $lines[] = $indent . $this->fluent($class, $config) . ',';
        }

        return implode("\n", $lines);
    }

    /**
     * The fluent chain for one prophet entry.
     *
     * @param  array<string, mixed>  $config
     */
    public function fluent(string $class, array $config): string
    {
        $chain = $this->classReference($class) . '::make()';

        if (array_key_exists('severity', $config)) {
            $severity = is_string($config['severity']) ? Severity::fromName($config['severity']) : null;
            unset($config['severity']);

            if ($severity === Severity::Off) {
                $chain .= '->disabled()';
            } elseif ($severity !== null) {
                $chain .= '->severity(Severity::' . $severity->name . ')';
            }
        }

        foreach ($config as $key => $value) {
            // Emit the typed fluent setter: `max_method_lines` => `->maxMethodLines(60)`.
            // Every key is callable in camelCase (BaseCommandment::__call), so this is
            // always valid; a `true` value is a flag (`->initializersOnly()`).
            $method = $this->camelCase((string) $key);

            $chain .= $value === true
                ? "->{$method}()"
                : "->{$method}(" . $this->export($value) . ')';
        }

        return $chain;
    }

    private function camelCase(string $key): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $key))));
    }

    /**
     * `JesseGall\CodeCommandments\Prophets\Backend\FooProphet` → `Backend\FooProphet::make()`,
     * matching the `use ...\Prophets;` alias the shipped config uses; an unknown
     * namespace falls back to the leading-backslash FQCN.
     */
    private function classReference(string $fqcn): string
    {
        $fqcn = ltrim($fqcn, '\\');

        if (str_starts_with($fqcn, self::PROPHET_NS)) {
            return substr($fqcn, strlen(self::PROPHET_NS));
        }

        return '\\' . $fqcn;
    }

    private function export(mixed $value): string
    {
        if (is_array($value)) {
            return $this->exportArray($value);
        }

        return var_export($value, true);
    }

    /**
     * @param  array<int|string, mixed>  $value
     */
    private function exportArray(array $value): string
    {
        $isList = array_is_list($value);
        $parts = [];

        foreach ($value as $k => $v) {
            $parts[] = $isList ? $this->export($v) : var_export((string) $k, true) . ' => ' . $this->export($v);
        }

        return '[' . implode(', ', $parts) . ']';
    }
}
