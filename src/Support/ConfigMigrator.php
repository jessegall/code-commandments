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
            $chain .= '->set(' . var_export((string) $key, true) . ', ' . $this->export($value) . ')';
        }

        return $chain;
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
