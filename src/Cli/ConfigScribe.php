<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use JesseGall\CodeCommandments\Ast\Codebase;
use PhpParser\Node\Expr\MethodCall;

/**
 * The config scribe — it WRITES the project's `.commandments/config.php`. It scaffolds a fresh
 * config with the auto-detected source roots baked into `$config->paths(...)`, and fills the paths
 * of an existing config that hasn't declared any. Like every scribe it edits PHP through the AST,
 * never as text: the `paths()` arguments are spliced by node offset, so the human's own lines are
 * left exactly as they were. {@see ConfigFile} is the read/edit twin (the `disable()` list).
 */
final class ConfigScribe
{
    public const string TEMPLATE = <<<'PHP'
        <?php

        declare(strict_types=1);

        use JesseGall\CodeCommandments\Config;

        /*
         | code-commandments configuration.
         |
         | `paths()` lists the source roots `judge` and `repent` scan — auto-detected from
         | composer.json (PSR-4 + app/src) on first run; edit it freely to adjust scope. Every
         | detector is enabled by default; turn rules off with `commandments disable/enable <sin>`
         | or by hand in the disable() call below.
         */

        return function (Config $config): void {
            // The source roots scanned (auto-detected; edit to adjust scope):
            $config->paths(%ROOTS%);

            // Silence rules — a Sin, a Detector, or a whole Skill class:
            $config->disable(
                // \JesseGall\CodeCommandments\Sins\Backend\SwallowCatch::class,
            );

            // Add your own:
            //   $config->detector(\App\Commandments\NoRawSqlDetector::class);
            //   $config->package(\App\Commandments\MyFrameworkPackage::class);
            //   $config->configure(fn (\JesseGall\CodeCommandments\Detectors\Frontend\DeepNestedDetector $d) => $d->maxDepth(10));
        };

        PHP;

    public function __construct(public readonly string $path) {}

    /**
     * The config scribe for a project — `<dir>/.commandments/config.php` (default the cwd).
     */
    public static function inProject(?string $dir = null): self
    {
        return new self(($dir ?? getcwd()) . '/.commandments/config.php');
    }

    /**
     * Write a fresh config with these roots baked into `paths()`, if none exists yet. Returns true
     * when it created one.
     *
     * @param  list<string>  $roots
     */
    public function scaffold(array $roots): bool
    {
        if (is_file($this->path)) {
            return false;
        }

        @mkdir(dirname($this->path), 0777, true);
        file_put_contents($this->path, $this->render($roots));

        return true;
    }

    /**
     * Fill an existing config's EMPTY `paths()` call with these roots — a no-op when it already
     * declares some, or has no `paths()` call to fill.
     *
     * @param  list<string>  $roots
     */
    public function ensurePaths(array $roots): void
    {
        $call = $this->pathsCall();

        if ($call !== null && $call->args === []) {
            $this->splice($call, $roots);
        }
    }

    /**
     * OVERWRITE the `paths()` call with these roots (scaffolding a fresh config when there is none)
     * — what `commandments paths` runs to regenerate the scan scope from a fresh detection.
     *
     * @param  list<string>  $roots
     */
    public function rewritePaths(array $roots): void
    {
        if (! is_file($this->path)) {
            $this->scaffold($roots);

            return;
        }

        $call = $this->pathsCall();

        if ($call !== null) {
            $this->splice($call, $roots);
        }
    }

    /**
     * @param  list<string>  $roots
     */
    public function render(array $roots): string
    {
        return str_replace('%ROOTS%', $this->renderRoots($roots), self::TEMPLATE);
    }

    /**
     * @param  list<string>  $roots
     */
    private function renderRoots(array $roots): string
    {
        return implode(', ', array_map(static fn (string $root): string => "'" . addslashes($root) . "'", $roots));
    }

    /**
     * Splice roots into the `paths()` call's arguments by node offset — leaving the parens and
     * everything around the call exactly as they were.
     *
     * @param  list<string>  $roots
     */
    private function splice(MethodCall $call, array $roots): void
    {
        $source = (string) file_get_contents($this->path);

        if ($call->args !== []) {
            $from = $call->args[0]->value->getStartFilePos();
            $to = end($call->args)->value->getEndFilePos() + 1;
        } else {
            $from = $to = $call->getEndFilePos(); // between the empty ()
        }

        file_put_contents($this->path, substr($source, 0, $from) . $this->renderRoots($roots) . substr($source, $to));
    }

    private function pathsCall(): ?MethodCall
    {
        if (! is_file($this->path)) {
            return null;
        }

        $match = Codebase::fromString((string) file_get_contents($this->path), $this->path)->whereMethod('paths')->first();

        return $match?->node instanceof MethodCall ? $match->node : null;
    }
}
