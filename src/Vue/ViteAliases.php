<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

use JesseGall\CodeCommandments\Vue\Expr\Expr;
use JesseGall\CodeCommandments\Vue\Expr\Parser;

/**
 * Discovers a project's import aliases from its Vite config so the {@see ModuleResolver} can
 * follow `@app/composables/useX` to a real file. The alias map is read STRUCTURALLY, never
 * scraped: {@see Script} locates the `resolve: { alias: { … } }` object by tokens, the
 * expression engine parses it into an AST, and each entry's value — `path.resolve(src, 'x')`,
 * `resolve(__dirname, 'y')`, or a bare base var — is evaluated against the project root by
 * walking the call/identifier nodes.
 *
 * Base variables resolve lazily through the same config (`const src = resolve(dir, 'js')`,
 * `const dir = dirname(fileURLToPath(import.meta.url))` → the project root). Anything the
 * walker can't reduce to a path is skipped, so an exotic config degrades to fewer aliases
 * (relative imports still resolve), never to a crash.
 */
final class ViteAliases
{
    private const array CONFIG_FILES = ['vite.config.ts', 'vite.config.js', 'vite.config.mjs', 'vite.config.mts'];

    /** Functions that yield the config's own directory — i.e. the project root. */
    private const array ROOT_FUNCTIONS = ['dirname', 'fileURLToPath'];

    /** Functions that JOIN a base directory with path segments. */
    private const array JOIN_FUNCTIONS = ['resolve', 'join'];

    /**
     * The alias map for a project root — `['@app' => '/abs/resources/js', …]`, or `[]` when
     * no Vite config or no alias block is found.
     *
     * @return array<string, string>
     */
    public static function discover(string $projectRoot): array
    {
        foreach (self::CONFIG_FILES as $file) {
            $path = $projectRoot . '/' . $file;

            if (is_file($path)) {
                return self::fromSource((string) file_get_contents($path), $projectRoot);
            }
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    public static function fromSource(string $source, string $projectRoot): array
    {
        $script = new Script($source);
        $block = $script->objectAfter('alias');

        if ($block === null) {
            return [];
        }

        $aliases = [];

        foreach (Parser::parse($block)->objectEntries() as $prefix => $value) {
            $relative = self::relativeDir($value, $script, []);

            if ($relative !== null) {
                $aliases[$prefix] = self::join($projectRoot, $relative);
            }
        }

        return $aliases;
    }

    /**
     * A config expression reduced to a directory relative to the project root — a base var
     * traced to its declaration, `resolve(base, 'a', 'b')` joined, a root-yielding call to
     * `''`. Null when it can't be reduced.
     *
     * @param  list<string>  $seen  base vars already being resolved (cycle guard)
     */
    private static function relativeDir(Expr $expression, Script $script, array $seen): ?string
    {
        return match ($expression->kind) {
            Expr::IDENTIFIER => self::identifierDir((string) $expression->get('name'), $script, $seen),
            Expr::LITERAL => self::literalPath($expression),
            Expr::CALL => self::callDir($expression, $script, $seen),
            default => null,
        };
    }

    /**
     * @param  list<string>  $seen
     */
    private static function identifierDir(string $name, Script $script, array $seen): ?string
    {
        if ($name === '__dirname') {
            return '';
        }

        if (in_array($name, $seen, true) || ($rhs = $script->declaratorValue($name)) === null) {
            return null;
        }

        return self::relativeDir(Parser::parse($rhs), $script, [...$seen, $name]);
    }

    /**
     * @param  list<string>  $seen
     */
    private static function callDir(Expr $call, Script $script, array $seen): ?string
    {
        $callee = self::calleeName($call);

        if (in_array($callee, self::ROOT_FUNCTIONS, true)) {
            return ''; // dirname(fileURLToPath(import.meta.url)) — the config dir is the root
        }

        if (! in_array($callee, self::JOIN_FUNCTIONS, true)) {
            return null;
        }

        $arguments = $call->get('arguments');
        $arguments = is_array($arguments) ? array_values($arguments) : [];

        if ($arguments === []) {
            return '';
        }

        $segments = [self::relativeDir($arguments[0], $script, $seen) ?? ''];

        foreach (array_slice($arguments, 1) as $argument) {
            if ($argument instanceof Expr && $argument->kind === Expr::LITERAL) {
                $segments[] = self::literalPath($argument);
            }
        }

        return self::joinSegments($segments);
    }

    private static function calleeName(Expr $call): ?string
    {
        $callee = $call->get('callee');

        if (! $callee instanceof Expr) {
            return null;
        }

        return match ($callee->kind) {
            Expr::IDENTIFIER => (string) $callee->get('name'),
            Expr::MEMBER => (string) $callee->get('property'),
            default => null,
        };
    }

    private static function literalPath(Expr $literal): string
    {
        $value = (string) $literal->get('value');

        return trim($value, '/');
    }

    /**
     * @param  list<string>  $segments
     */
    private static function joinSegments(array $segments): string
    {
        $parts = array_filter($segments, static fn (string $part): bool => $part !== '');

        return implode('/', $parts);
    }

    private static function join(string $root, string $relative): string
    {
        $base = rtrim($root, '/');

        return $relative === '' ? $base : "{$base}/{$relative}";
    }
}
