<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Dashboard;

use JesseGall\CodeCommandments\Sins\Catalog;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * The Sins dashboard the agent journal shows on the plugin's card: how many sins there are, which are
 * most common, and for each sin the files it is in and every place it was found, explained. Written in
 * the journal's dashboard format — pages of nodes, each able to open another page.
 */
final readonly class SinsDashboard
{
    /**
     * Where the journal reads the dashboard, inside the plugin's data folder.
     */
    public const string FILE = 'dashboards/sins.json';

    /**
     * How many sins the overview's bars show.
     */
    private const int TOP = 20;

    /**
     * @param  list<StoredFinding>  $findings
     */
    public function __construct(private array $findings) {}

    public function render(): Dashboard
    {
        $bySin = [];

        foreach ($this->findings as $finding) {
            $bySin[$finding->sin][$finding->file][] = $finding;
        }

        uasort($bySin, static fn (array $one, array $other): int => self::count($other) <=> self::count($one));
        $pages = ['overview' => ['title' => 'Overview', 'view' => $this->overview($bySin)]];

        foreach ($bySin as $sin => $files) {
            $pages["sin/{$sin}"] = ['title' => $sin, 'view' => $this->sinPage((string) $sin, $files)];

            foreach ($files as $file => $found) {
                $pages["sin/{$sin}/{$file}"] = ['title' => "{$sin} in " . basename((string) $file), 'view' => $this->filePage((string) $sin, (string) $file, $found)];
            }
        }

        return new Dashboard('Code Commandments', 'overview', $pages);
    }

    /**
     * @param  array<string, array<string, list<StoredFinding>>>  $bySin
     * @return array<string, mixed>
     */
    private function overview(array $bySin): array
    {
        $total = count($this->findings);
        $files = count(array_unique(array_map(static fn (StoredFinding $finding): string => $finding->file, $this->findings)));
        $skills = count(array_unique(array_map(static fn (StoredFinding $finding): string => $finding->skill, $this->findings)));
        $tone = $total === 0 ? 'good' : 'danger';

        $tiles = ['type' => 'row', 'gap' => 12, 'children' => [
            ['type' => 'stat', 'label' => 'Sins', 'value' => (string) $total, 'tone' => $tone, 'note' => count($bySin) . ' different rules'],
            ['type' => 'stat', 'label' => 'Files', 'value' => (string) $files, 'tone' => $total === 0 ? 'good' : 'warn'],
            ['type' => 'stat', 'label' => 'Skills', 'value' => (string) $skills, 'note' => 'the skills that teach the fixes'],
        ]];

        if ($total === 0) {
            return ['type' => 'stack', 'gap' => 16, 'children' => [$tiles, ['type' => 'text', 'body' => 'No sins in the last judged files.']]];
        }

        $bars = array_map(static fn (string $sin, array $files) => [
            'label' => $sin,
            'value' => self::count($files),
            'note' => count($files) . (count($files) === 1 ? ' file' : ' files'),
            'tone' => 'danger',
            'open' => "sin/{$sin}",
        ], array_keys($bySin), array_values($bySin));

        return ['type' => 'stack', 'gap' => 16, 'children' => [
            $tiles,
            ['type' => 'bars', 'title' => 'Most common sins', 'unit' => 'sins', 'items' => array_slice($bars, 0, self::TOP)],
        ]];
    }

    /**
     * @param  array<string, list<StoredFinding>>  $files
     * @return array<string, mixed>
     */
    private function sinPage(string $sin, array $files): array
    {
        uasort($files, static fn (array $one, array $other): int => count($other) <=> count($one));
        $rows = array_map(static fn (string $file, array $found) => [
            'cells' => [$file, (string) count($found)],
            'open' => "sin/{$sin}/{$file}",
        ], array_keys($files), array_values($files));

        return ['type' => 'stack', 'gap' => 16, 'children' => [
            ['type' => 'heading', 'text' => $sin, 'level' => 1],
            ['type' => 'text', 'body' => $this->explanation($sin)],
            ['type' => 'table', 'columns' => ['File', 'Sins'], 'rows' => $rows],
        ]];
    }

    /**
     * @param  list<StoredFinding>  $found
     * @return array<string, mixed>
     */
    private function filePage(string $sin, string $file, array $found): array
    {
        usort($found, static fn (StoredFinding $one, StoredFinding $other): int => $one->line <=> $other->line);
        $places = array_map(static fn (StoredFinding $finding) => [
            'type' => 'file',
            'path' => $file,
            'line' => $finding->line,
            'label' => "line {$finding->line} — {$finding->scope}",
        ], $found);

        return ['type' => 'stack', 'gap' => 16, 'children' => [
            ['type' => 'heading', 'text' => "{$sin} in {$file}", 'level' => 1],
            ['type' => 'card', 'title' => count($found) === 1 ? 'Where' : count($found) . ' places', 'children' => $places],
            ['type' => 'text', 'body' => $this->explanation($sin)],
        ]];
    }

    /**
     * What the sin is, why it is one and how it is fixed, in the words the rule itself carries.
     */
    private function explanation(string $sin): string
    {
        $rule = self::rules()[$sin] ?? null;

        if ($rule === null) {
            return "A rule of this project's own — its description lives in `.commandments/custom/`.";
        }

        $suggestion = $rule->suggestion();

        return "**What it is:** {$rule->description()}\n\n**The rule:** {$rule->rule()}"
            . ($suggestion === null ? '' : "\n\n**How to fix it:** {$suggestion}")
            . "\n\n**Learn more:** `commandments info {$sin}`";
    }

    /**
     * @param  array<string, list<StoredFinding>>  $files
     */
    private static function count(array $files): int
    {
        return array_sum(array_map(count(...), $files));
    }

    /**
     * Every shipped sin, by name.
     *
     * @return array<string, Sin>
     */
    private static function rules(): array
    {
        static $rules = null;

        return $rules ??= array_column(array_map(static fn (Sin $sin) => [$sin->name(), $sin], Catalog::every()), 1, 0);
    }
}
