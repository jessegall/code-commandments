<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use JesseGall\CodeCommandments\Cli\Help\Help;
use JesseGall\CodeCommandments\Sins\Catalog as Sins;
use JesseGall\CodeCommandments\Sins\Scaffold as ScaffoldFile;
use JesseGall\CodeCommandments\Sins\ScaffoldTarget;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\PhpTypes\Option;
/**
 * Generates reusable helpers (scaffolds) that a sin's fix needs, injected with namespace into source root.
 * Pairs with `repent` for call-site fixing; idempotent and skips existing files.
 */
final class Scaffold implements Command
{
    /**
     * Where a frontend scaffold (a Vue component) is written — the JS source root, by convention.
     */
    private const string FRONTEND_ROOT = 'resources/js';

    public function names(): array
    {
        return ['scaffold'];
    }

    public function help(): Help
    {
        return Help::of("Generate the reusable helper a sin's fix uses — written into your source root with its namespace injected. Idempotent: an existing file is skipped.")
            ->form('scaffold', 'generate every sin\'s scaffold that is missing')
            ->form('scaffold --sin=NAME', 'generate one sin\'s scaffold (lenient name match)')
            ->option('--sin=NAME', 'only scaffold for this sin')
            ->option('--dry-run', 'print what would be created, and its contents, without writing');
    }

    public function run(Input $input): int
    {
        $sin = $input->option('sin');
        $dryRun = $input->hasFlag('dry-run');

        $root = $this->sourceRoot();

        if ($root === null) {
            fwrite(STDERR, "Could not resolve a PSR-4 source root from composer.json (run from the project root).\n");

            return 2;
        }

        [$dir, $rootNamespace] = $root;
        $created = [];
        $skipped = [];

        foreach (Sins::every() as $candidate) {
            if ($sin->isSomeAnd(static fn (string $query): bool => ! $candidate->matches($query))) {
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

                // Skip when it already exists at the target path, OR — for a frontend construct — when a
                // component of that name already lives ANYWHERE under the JS root (a project that ships
                // `ui/switch-case/SwitchCase.vue` must not get a duplicate `components/SwitchCase.vue`;
                // the repented code imports the real one, so a second copy is dead weight that itself
                // trips control-flow-on-element).
                if (is_file($target) || ($frontend && ($existing = $this->existingFrontendComponent($scaffold->path)) !== null)) {
                    $skipped[] = $existing ?? $target;

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
     * The path of an existing component with this construct's name (the scaffold sub-path's file stem,
     * e.g. `SwitchCase`) ANYWHERE under the JS root, or null when none. So a project that already ships
     * the construct — under any folder — is not handed a duplicate copy.
     */
    private function existingFrontendComponent(string $scaffoldPath): ?string
    {
        $root = getcwd() . '/' . self::FRONTEND_ROOT;

        if (! is_dir($root)) {
            return null;
        }

        $wanted = basename($scaffoldPath); // e.g. `SwitchCase.vue`
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ($file->getFilename() === $wanted) {
                return $file->getPathname();
            }
        }

        return null;
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
    private function report(array $created, array $skipped, Option $sin, bool $dryRun): int
    {
        if ($created === [] && $skipped === []) {
            $where = $sin->mapOr('No sin', static fn (string $query): string => "--sin={$query}");
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


    private function out(string $text): void
    {
        fwrite(STDOUT, $text);
    }
}
