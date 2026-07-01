<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use JesseGall\CodeCommandments\Sins\Catalog as Sins;
use JesseGall\CodeCommandments\Sins\Scaffold as ScaffoldFile;
use JesseGall\CodeCommandments\Sins\ScaffoldTarget;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * `commandments scaffold [--sin=NAME] [--dry-run]`
 *
 * Generates the reusable helper(s) a sin's fix needs — the construct its
 * {@see Sin::suggestion} names ({@see Sin::scaffolds}) — into the consumer's source root,
 * with their namespace injected. It CREATES a helper; `repent` fixes call SITES, so they
 * compose: scaffold the construct, then `repent` to use it. Idempotent — an existing file
 * is skipped, never overwritten.
 */
final class Scaffold
{
    /** Where a frontend scaffold (a Vue component) is written — the JS source root, by convention. */
    private const string FRONTEND_ROOT = 'resources/js';

    public function run(array $args): int
    {
        $sin = $this->option($args, '--sin=');
        $dryRun = in_array('--dry-run', $args, true);

        $root = $this->sourceRoot();

        if ($root === null) {
            fwrite(STDERR, "Could not resolve a PSR-4 source root from composer.json (run from the project root).\n");

            return 2;
        }

        [$dir, $rootNamespace] = $root;
        $created = [];
        $skipped = [];

        foreach (Sins::every() as $candidate) {
            if ($sin !== null && ! $candidate->matches($sin)) {
                continue;
            }

            foreach ($candidate->scaffolds() as $scaffold) {
                // A frontend scaffold (a Vue component) lands under the JS source root with no
                // namespace to inject; a backend one under the PSR-4 root with its namespace.
                $frontend = $scaffold->target === ScaffoldTarget::Frontend;
                $target = $frontend
                    ? getcwd() . '/' . self::FRONTEND_ROOT . '/' . $scaffold->path
                    : "{$dir}/{$scaffold->path}";
                $code = $scaffold->render($frontend ? '' : $this->namespaceFor($rootNamespace, $scaffold));

                if (is_file($target)) {
                    $skipped[] = $target;

                    continue;
                }

                if ($dryRun) {
                    $this->out("\033[2m↳ would create {$target}\033[0m\n{$code}\n");

                    continue;
                }

                @mkdir(dirname($target), 0755, true);
                file_put_contents($target, $code);
                $created[] = $target;
            }
        }

        return $this->report($created, $skipped, $sin, $dryRun);
    }

    /**
     * The namespace a scaffold's file declares — the root namespace plus the directories
     * of its sub-path (`App` + `Support/Invokable.php` → `App\Support`).
     */
    private function namespaceFor(string $rootNamespace, ScaffoldFile $scaffold): string
    {
        $sub = dirname($scaffold->path);

        return $sub === '.' ? $rootNamespace : $rootNamespace . '\\' . str_replace('/', '\\', $sub);
    }

    /**
     * The consumer's primary PSR-4 root from `composer.json` — its directory and root
     * namespace. Prefers `app/` (the Laravel convention), else the first mapping.
     *
     * @return array{0: string, 1: string}|null  [dir, rootNamespace]
     */
    private function sourceRoot(): ?array
    {
        $composer = getcwd() . '/composer.json';

        if (! is_file($composer)) {
            return null;
        }

        $psr4 = json_decode((string) file_get_contents($composer), true)['autoload']['psr-4'] ?? [];

        foreach ($psr4 as $namespace => $directory) {
            if (rtrim((string) $directory, '/') === 'app') {
                return ['app', rtrim((string) $namespace, '\\')];
            }
        }

        $namespace = array_key_first($psr4);

        return $namespace === null ? null : [rtrim((string) $psr4[$namespace], '/'), rtrim((string) $namespace, '\\')];
    }

    /**
     * @param  list<string>  $created
     * @param  list<string>  $skipped
     */
    private function report(array $created, array $skipped, ?string $sin, bool $dryRun): int
    {
        if ($created === [] && $skipped === []) {
            $where = $sin === null ? 'No sin' : "--sin={$sin}";
            $this->out("\033[2m{$where} provides a scaffold (most fixes are domain-specific).\033[0m\n");

            return 0;
        }

        if ($dryRun) {
            return 0;
        }

        if ($created !== []) {
            $this->out("\033[32m✓ Scaffolded " . count($created) . " " . (count($created) === 1 ? 'file' : 'files') . ".\033[0m\n");

            foreach ($created as $file) {
                $this->out("  {$file}\n");
            }
        }

        foreach ($skipped as $file) {
            $this->out("\033[2m↳ exists, skipped {$file}\033[0m\n");
        }

        return 0;
    }

    private function option(array $args, string $prefix): ?string
    {
        foreach ($args as $arg) {
            if (str_starts_with($arg, $prefix)) {
                return substr($arg, strlen($prefix));
            }
        }

        return null;
    }

    private function out(string $text): void
    {
        fwrite(STDOUT, $text);
    }
}
