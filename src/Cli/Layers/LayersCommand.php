<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Layers;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Support\NamespaceGraph;
use JesseGall\CodeCommandments\Cli\Command;
use JesseGall\CodeCommandments\Cli\Help\Help;
use JesseGall\CodeCommandments\Cli\Help\HelpScreen;
use JesseGall\CodeCommandments\Cli\Config\ConfigFile;
use JesseGall\CodeCommandments\Cli\Config\ConfigScribe;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Cli\Config\SourceRoots;

/**
 * `commandments layers` — read the dependency stack a project ALREADY has and propose the
 * declaration for it, since the layer rule is inert until one exists and nobody writes that from a
 * blank file. The default proposal is today's shape — every namespace with what it already uses, so
 * it is green the moment it is written and refuses only the next arrow somewhere new. `--floor` is a
 * smaller start (the namespaces others depend on that depend on nothing), which tightens as the
 * layers above it are declared, a layer being free to use any namespace still undeclared.
 */
final class LayersCommand implements Command
{
    public function names(): array
    {
        return ['layers'];
    }

    public function help(): Help
    {
        return Help::of('Read the dependency stack this project ALREADY has and propose the layer declaration for it — the rule is inert until one is declared, and nobody writes that from a blank file.')
            ->form('layers [path]', "propose today's shape: every namespace with what it already uses")
            ->form('layers [path] --floor', 'propose only the bottom — what others depend on and that depends on nothing')
            ->form('layers [path] --write', 'add the proposal to .commandments/config.php')
            ->form('layers add <Namespace> [--may-use=A,B]', 'declare a new layer, or widen a declared one, in place')
            ->form('layers allow <Layer> <Target>', 'one more arrow, in place')
            ->option('--floor', 'propose only the bottom layer')
            ->option('--write', 'write the proposal into .commandments/config.php')
            ->option('--refresh', 'with --write, regenerate a block that is already declared')
            ->option('--may-use=A,B', 'with `add`, the namespaces the new layer may depend on')
            ->note('Once a stack is declared the proposal refuses to overwrite it — a GROWING codebase edits it '
                . 'INCREMENTALLY with `add`/`allow`, or regenerates the whole block from today\'s shape with `--write --refresh`. Every edit goes through the AST and replaces only the ->layer(...) chain, keeping your config\'s own formatting.');
    }

    public function run(Input $input): int
    {
        // The incremental moves come first: they edit the DECLARED stack and never scan, so a growing
        // codebase does not have to re-propose (and hand-delete) the whole block to add one arrow.
        return match ($input->firstArgument()->unwrapOr('propose')) {
            'add' => $this->add($input),
            'allow' => $this->allow($input),
            default => $this->propose($input),
        };
    }

    /**
     * `layers add <Namespace> [--may-use=A,B]` — declare a layer, or widen one that is already
     * declared. Appends to the existing block in place; the rest of the config is untouched.
     */
    private function add(Input $input): int
    {
        $namespace = $input->arguments()[1] ?? null;

        if ($namespace === null) {
            return $this->usage('layers add <Namespace> [--may-use=A,B]');
        }

        $config = ConfigFile::inProject();
        $layers = $config->layers();
        $namespace = self::normalise($namespace);
        $existing = $layers[$namespace] ?? [];
        $added = array_values(array_diff(self::namespaces($input->list('may-use')), $existing));

        $declared = array_key_exists($namespace, $layers);
        $layers[$namespace] = [...$existing, ...$added];

        return $this->commit($config, $layers, self::addReport($namespace, $added, $declared));
    }

    /**
     * What `add` did, said plainly: a new layer, a widened one, or nothing left to do.
     *
     * @param  list<string>  $added
     */
    private static function addReport(string $namespace, array $added, bool $declared): string
    {
        if (! $declared) {
            return "✓ declared {$namespace}" . ($added === [] ? ' (reaching nothing yet)' : ' → ' . implode(', ', $added));
        }

        if ($added === []) {
            return "• {$namespace} already reaches those layers — nothing to add";
        }

        return "✓ {$namespace} may now use " . implode(', ', $added);
    }

    /**
     * `layers allow <Layer> <Target>` — one arrow, deliberately. The layer must already be declared:
     * silently inventing it would hide a typo as a new stack.
     */
    private function allow(Input $input): int
    {
        $arguments = $input->arguments();

        if (count($arguments) < 3) {
            return $this->usage('layers allow <Layer> <Target>');
        }

        $from = self::normalise($arguments[1]);
        $to = self::normalise($arguments[2]);

        $config = ConfigFile::inProject();
        $layers = $config->layers();

        if (! array_key_exists($from, $layers)) {
            return $this->fail("{$from} is not a declared layer — run `commandments layers add {$from}` first, or check the spelling.");
        }

        if (in_array($to, $layers[$from], true)) {
            return $this->done("• {$from} may already use {$to}");
        }

        $layers[$from][] = $to;

        return $this->commit($config, $layers, "✓ {$from} may now use {$to}");
    }

    /**
     * Write $layers back over the declared block and report $message.
     *
     * @param  array<string, list<string>>  $layers
     */
    private function commit(ConfigFile $config, array $layers, string $message): int
    {
        if (! $config->rewriteLayers($layers)) {
            return $this->fail('.commandments/config.php declares no layers yet — run `commandments layers --write` to propose the stack first.');
        }

        return $this->done($message);
    }

    private function propose(Input $input): int
    {
        // The scan can be pointed anywhere; the config being proposed to is always THIS project.
        $given = $input->firstArgument();
        $project = (string) getcwd();
        $roots = new SourceRoots()->resolve($given->unwrapOr($project), $given->isSome());
        $graph = NamespaceGraph::forCodebase(Codebase::scan($roots));

        $order = $graph->dependencyOrder();
        $floorOnly = $input->hasFlag('floor');
        $proposed = $floorOnly ? $graph->floorShape() : $graph->currentShape();

        $this->report($graph, $order, array_keys($graph->floorShape()), $proposed, $floorOnly);

        if ($proposed === []) {
            return 0;
        }

        return $input->hasFlag('write') ? $this->write($project, $proposed, $input->hasFlag('refresh')) : 0;
    }

    /**
     * @param  array{ordered: list<string>, cyclic: list<string>}  $order
     * @param  list<string>  $foundation
     * @param  array<string, list<string>>  $proposed
     */
    private function report(NamespaceGraph $graph, array $order, array $foundation, array $proposed, bool $floorOnly): void
    {
        $total = count($order['ordered']) + count($order['cyclic']);

        $this->line("\033[1mNamespace layers\033[0m — {$total} namespaces");

        $this->line('');
        $this->line(sprintf(
            "  \033[32m%d\033[0m are depended on but depend on nothing of yours — the floor your stack rests on",
            count($foundation),
        ));

        foreach (array_slice($foundation, 0, 12) as $namespace) {
            $this->line("      {$namespace}");
        }

        if (count($foundation) > 12) {
            $this->line(sprintf('      … and %d more', count($foundation) - 12));
        }

        if ($order['cyclic'] !== []) {
            $this->line('');
            $this->line(sprintf(
                "  \033[33m%d\033[0m sit in a cycle — declared below as they stand, each permitting the other:",
                count($order['cyclic']),
            ));

            foreach (array_slice($graph->mutualPairs(), 0, 8) as [$from, $to]) {
                $this->line("      {$from}  ->  {$to}");
            }

            $this->line("    \033[2mrun `commandments judge --sin=namespace-cycle` for the exact arrows to cut\033[0m");
        }

        if ($proposed === []) {
            $this->line('');
            $this->line($floorOnly
                ? '  Nothing at the floor — every namespace here reaches another. Drop --floor for the whole shape.'
                : '  Nothing to propose — nothing here references anything else of yours.');

            return;
        }

        $this->line('');
        $this->line($floorOnly
            ? sprintf('  Proposed declaration — the floor (%d namespaces):', count($proposed))
            : sprintf('  Proposed declaration — today\'s shape, held (%d namespaces):', count($proposed)));
        $this->line('');
        $this->line(self::render($proposed));
        $this->line('');
        $this->line("  \033[2mA starting point to EDIT, not a verdict: everything already here passes, so this"
            . "\n  holds the architecture where it stands and refuses the NEXT arrow somewhere new.\033[0m");
        $this->line("  \033[2mre-run with --write to add it to .commandments/config.php"
            . ($floorOnly ? '' : ', or --floor for just the bottom') . "\033[0m");
    }

    /**
     * @param  array<string, list<string>>  $layers
     */
    private function write(string $path, array $layers, bool $refresh): int
    {
        $config = new ConfigFile(\JesseGall\CodeCommandments\Workspace::config($path));

        if ($refresh && $config->rewriteLayers($layers)) {
            $this->line('');
            $this->line("\033[32m✓ refreshed the declaration in .commandments/config.php\033[0m");
            $this->line("  \033[2mthe stack as it stands today — read the diff before you commit it\033[0m");

            return 0;
        }

        $written = new ConfigScribe(\JesseGall\CodeCommandments\Workspace::config($path))->ensureLayers(self::render($layers));

        $this->line('');
        $this->line($written
            ? "\033[32m✓ written to .commandments/config.php\033[0m"
            : "\033[33m• config.php already declares layers — left untouched.\033[0m"
                . "\n  \033[2mAdd to it instead: `layers add <Namespace> [--may-use=A,B]`, `layers allow <Layer> <Target>`,"
                . "\n  or `layers --write --refresh` to regenerate the whole block from today's shape.\033[0m");

        return 0;
    }

    /**
     * A comma-separated option as a list of namespaces — `--may-use=App\\A,App\\B`.
     *
     * @return list<string>
     */
    private static function namespaces(array $option): array
    {
        return array_values(array_filter(array_map(self::normalise(...), $option)));
    }

    /**
     * A namespace as the config spells it — trimmed, without a leading separator, and with the
     * double backslashes a shell leaves behind collapsed.
     */
    private static function normalise(string $namespace): string
    {
        return ltrim(str_replace('\\\\', '\\', trim($namespace)), '\\');
    }

    private function usage(string $form): int
    {
        return HelpScreen::usage($this, "Incomplete — that form reads `commandments {$form}`.");
    }

    private function fail(string $message): int
    {
        fwrite(STDERR, "\033[31m✗ {$message}\033[0m\n");

        return 2;
    }

    private function done(string $message): int
    {
        $this->line("\033[32m{$message}\033[0m");

        return 0;
    }

    /**
     * The declaration as source: one `->layer(...)` per namespace, in dependency order.
     *
     * @param  array<string, list<string>>  $layers
     */
    public static function render(array $layers): string
    {
        return '    $config->configure(fn (NamespaceDependencyDetector $detector) => $detector'
            . ConfigFile::renderChain($layers, '        ')
            . ');';
    }

    private function line(string $message): void
    {
        fwrite(STDOUT, $message . "\n");
    }
}
