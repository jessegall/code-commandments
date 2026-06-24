<?php

declare(strict_types=1);

/**
 * Stamp `@method` IntelliSense hints onto every prophet, derived from the config
 * keys it actually reads (`$this->config('key', default)`). Each key becomes a
 * camelCase fluent setter (backed by BaseCommandment::__call), so a consumer writing
 * `LongMethodProphet::make()->` gets autocomplete for `maxMethodLines(int $value)`.
 *
 * Idempotent: it replaces the generated `@method` block (between markers) on each run.
 * Run via `composer hints` after adding/removing a prophet config key.
 */

const BEGIN = '@method-generated-start';
const END = '@method-generated-end';

$root = dirname(__DIR__);
$files = array_merge(
    glob($root . '/src/Prophets/Backend/*Prophet.php') ?: [],
    glob($root . '/src/Prophets/Frontend/*Prophet.php') ?: [],
);

$stamped = 0;

foreach ($files as $file) {
    $source = file_get_contents($file);

    if ($source === false) {
        continue;
    }

    $keys = collectConfigKeys($source);

    // `severity` is handled by the real ->severity()/->disabled() methods.
    unset($keys['severity']);

    $rewritten = stampHints($source, $keys);

    if ($rewritten !== null && $rewritten !== $source) {
        file_put_contents($file, $rewritten);
        $stamped++;
        echo 'hinted ' . count($keys) . ' setter(s): ' . basename($file) . "\n";
    }
}

echo "Stamped @method hints on {$stamped} prophet(s).\n";

/**
 * @return array<string, string>  config key => inferred PHP type
 */
function collectConfigKeys(string $source): array
{
    preg_match_all("/\\\$this->config\\(\\s*'([a-z0-9_]+)'\\s*(?:,\\s*([^),]+))?\\)/i", $source, $matches, PREG_SET_ORDER);

    $keys = [];

    foreach ($matches as $match) {
        $key = $match[1];
        $default = trim($match[2] ?? '');
        $keys[$key] = inferType($key, $default, $source);
    }

    ksort($keys);

    return $keys;
}

function inferType(string $key, string $default, string $source): string
{
    if ($default === 'true' || $default === 'false') {
        return 'bool';
    }

    if (preg_match('/^-?\d+$/', $default)) {
        return 'int';
    }

    if (preg_match('/^-?\d*\.\d+$/', $default)) {
        return 'float';
    }

    if (str_starts_with($default, '[') || str_starts_with($default, 'T_Array')) {
        return 'array';
    }

    if (preg_match("/^'.*'$/", $default) || str_contains($default, 'empty()')) {
        return 'string';
    }

    // `self::CONST` — resolve the constant's declared value (accurate: array vs string).
    if (preg_match('/^self::([A-Z0-9_]+)$/', $default, $cm)) {
        if (preg_match('/const\s+' . preg_quote($cm[1], '/') . '\s*=\s*([^;]+);/', $source, $vm)) {
            $value = trim($vm[1]);

            return match (true) {
                str_starts_with($value, '[') => 'array',
                str_starts_with($value, "'"), str_starts_with($value, '"') => 'string',
                $value === 'true' || $value === 'false' => 'bool',
                preg_match('/^-?\d+$/', $value) === 1 => 'int',
                preg_match('/^-?\d*\.\d+$/', $value) === 1 => 'float',
                default => 'mixed',
            };
        }
    }

    // No default / unresolved: infer from the key's shape. Explicit plural suffixes
    // are arrays; singular type-ish keys are strings; otherwise mixed.
    if (preg_match('/(_methods|_bases|_classes|_suffixes|_patterns|_objects|_enums|_markers|_functions|_names|^exclude$|^allow$)$/', $key)) {
        return 'array';
    }

    if (preg_match('/(_class|_trait|_method|_accessor|_suffix|_base|_namespace)$/', $key)) {
        return 'string';
    }

    return 'mixed';
}

/**
 * Insert/replace the generated `@method` block inside the class docblock that sits
 * directly above the `class X extends …Commandment` declaration.
 */
function stampHints(string $source, array $keys): ?string
{
    if (! preg_match('/\n((?:final )?(?:abstract )?class \w+Prophet\b)/', $source, $m, PREG_OFFSET_CAPTURE)) {
        return null;
    }

    $classPos = $m[1][1];

    // The docblock immediately preceding the class (allow an attribute line between).
    if (! preg_match('~(/\*\*.*?\*/)\s*(?:\#\[[^\]]*\]\s*)*$~s', substr($source, 0, $classPos), $dm, PREG_OFFSET_CAPTURE)) {
        return null;
    }

    $docblock = $dm[1][0];
    $docStart = $dm[1][1];
    $docEnd = $docStart + strlen($docblock);

    $hintLines = [' * ' . BEGIN];

    foreach ($keys as $key => $type) {
        $method = camel($key);
        $arg = $type === 'bool' ? "bool \$on = true" : "{$type} \$value";
        $hintLines[] = " * @method static {$method}({$arg})";
    }

    $hintLines[] = ' * ' . END;
    $block = implode("\n", $hintLines);

    // Strip any prior generated block first.
    $body = preg_replace('# \* ' . preg_quote(BEGIN, '#') . '.*? \* ' . preg_quote(END, '#') . '\n?#s', '', $docblock);
    $body = $body === null ? $docblock : $body;

    if ($keys === []) {
        $newDoc = $body; // nothing to hint
    } else {
        // Insert the block just before the closing `*/`.
        $newDoc = preg_replace('#\n \*/\s*$#', "\n *\n{$block}\n */", rtrim($body));
    }

    return substr($source, 0, $docStart) . $newDoc . substr($source, $docEnd);
}

function camel(string $key): string
{
    return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $key))));
}
