<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Layers;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Support\NamespaceGraph;
use JesseGall\CodeCommandments\Cli\Command;
use JesseGall\CodeCommandments\Cli\Config\ConfigScribe;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Cli\Judge\SourceRoots;

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

    public function run(Input $input): int
    {
        // The scan can be pointed anywhere; the config being proposed to is always THIS project.
        $given = $input->firstArgument();
        $project = (string) getcwd();
        $roots = new SourceRoots()->resolve($given ?? $project, $given !== null);
        $graph = NamespaceGraph::forCodebase(Codebase::scan($roots));

        $order = $graph->dependencyOrder();
        $floorOnly = $input->hasFlag('floor');
        $proposed = $floorOnly ? $graph->floorShape() : $graph->currentShape();

        $this->report($graph, $order, array_keys($graph->floorShape()), $proposed, $floorOnly);

        if ($proposed === []) {
            return 0;
        }

        return $input->hasFlag('write') ? $this->write($project, $proposed) : 0;
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
    private function write(string $path, array $layers): int
    {
        $written = new ConfigScribe(\JesseGall\CodeCommandments\Workspace::config($path))->ensureLayers(self::render($layers));

        $this->line('');
        $this->line($written
            ? "\033[32m✓ written to .commandments/config.php\033[0m"
            : "\033[33m• config.php already declares layers — left untouched\033[0m");

        return 0;
    }

    /**
     * The declaration as source: one `->layer(...)` per namespace, in dependency order.
     *
     * @param  array<string, list<string>>  $layers
     */
    public static function render(array $layers): string
    {
        $lines = ['    $config->configure(fn (NamespaceDependencyDetector $detector) => $detector'];
        $calls = [];

        foreach ($layers as $namespace => $uses) {
            $may = $uses === [] ? '' : ', mayUse: [' . implode(', ', array_map(self::quote(...), $uses)) . ']';
            $calls[] = '        ->layer(' . self::quote((string) $namespace) . $may . ')';
        }

        $calls[count($calls) - 1] .= ');';

        return implode("\n", [...$lines, ...$calls]);
    }

    private static function quote(string $namespace): string
    {
        return "'" . str_replace('\\', '\\\\', $namespace) . "'";
    }

    private function line(string $message): void
    {
        fwrite(STDOUT, $message . "\n");
    }
}
