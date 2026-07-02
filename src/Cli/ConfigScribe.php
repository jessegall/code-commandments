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
         | paths()   — the source roots judge and repent scan (auto-detected on first run; edit to
         |             adjust scope, or run `commandments config reindex`).
         | disable() — silence a rule by its Sin, Detector, or whole Skill class (or use
         |             `commandments disable/enable <sin>`).
         |
         | Extend it inside the closure:
         |   $config->detector(\App\Commandments\NoRawSqlDetector::class);        — add your own finder
         |   $config->package(\App\Commandments\MyFrameworkPackage::class);       — register its exemptions
         |   $config->configure(fn (DeepNestedDetector $d) => $d->maxDepth(10));  — tune a threshold
         */

        return function (Config $config): void {
            $config->paths(%ROOTS%);

            $config->disable(
                // \JesseGall\CodeCommandments\Sins\Backend\SwallowCatch::class,
            );
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
     * Inject a `$config->planExecution(...)` block if the config has none yet — self-healing the
     * plan-execution surface into an existing consumer on `composer update`, the way {@see ensurePaths}
     * fills the scan roots. Idempotent: a config that already declares `planExecution` (the human's or
     * a prior sync's) is left untouched. The block is spliced BEFORE the `disable()` call by node
     * offset — the human's own lines stay exactly as they were — and carries $completionChecks
     * (inferred from the project's scripts by {@see ChecksInference}) as its `onComplete` gate, with
     * the other knobs as commented examples so the surface is discoverable.
     *
     * @param  list<string>  $completionChecks
     */
    public function ensurePlanExecution(array $completionChecks): void
    {
        if (! is_file($this->path) || $this->hasCall('planExecution')) {
            return;
        }

        $anchor = $this->call('disable') ?? $this->call('paths');

        if ($anchor === null) {
            return; // A config edited past recognition — nothing safe to anchor to.
        }

        $source = (string) file_get_contents($this->path);
        $at = $anchor->getStartFilePos();

        file_put_contents($this->path, substr($source, 0, $at) . $this->renderPlanExecution($completionChecks) . substr($source, $at));
    }

    /**
     * The `planExecution()` block spliced in before the anchor call. Its first line takes the
     * anchor's existing indent; it ends with a blank line + a 4-space indent so the anchor stays
     * exactly where it was. Explicit lines (not a heredoc) so the emitted indentation is exact.
     *
     * @param  list<string>  $checks
     */
    public function renderPlanExecution(array $checks): string
    {
        $onComplete = $checks === []
            ? "// \$plan->onComplete('composer test');            // the end gate; judge --branch runs after"
            : '$plan->onComplete(' . $this->renderRoots($checks) . ');';

        $lines = [
            '$config->planExecution(function (\JesseGall\CodeCommandments\PlanExecution $plan): void {',
            "        // \$plan->branchFrom('main')->branchPrefix('plan/')->pushEachPhase();  // branch + push cadence",
            '        // $plan->keepGoing();                          // re-nudge on stop until `plan done`',
            "        // \$plan->onStart('composer install');          // once, before the first phase",
            "        // \$plan->eachPhase('composer lint');           // after each phase — keep it fast",
            "        {$onComplete}",
            '    });',
        ];

        return implode("\n", $lines) . "\n\n    ";
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
        return $this->call('paths');
    }

    /**
     * The first `$config-><method>(...)` call node in the config, or null when there is none.
     */
    private function call(string $method): ?MethodCall
    {
        if (! is_file($this->path)) {
            return null;
        }

        $match = Codebase::fromString((string) file_get_contents($this->path), $this->path)->whereMethod($method)->first();

        return $match?->node instanceof MethodCall ? $match->node : null;
    }

    private function hasCall(string $method): bool
    {
        return $this->call($method) !== null;
    }
}
