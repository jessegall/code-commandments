<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use JesseGall\CodeCommandments\Cli\Help\Help;
use JesseGall\CodeCommandments\Cli\Help\HelpScreen;
use JesseGall\CodeCommandments\Detectors\Catalog;
use JesseGall\CodeCommandments\Detectors\CrossFileSet;
use JesseGall\CodeCommandments\Workspace;

use JesseGall\CodeCommandments\Agents\Agent;
use JesseGall\CodeCommandments\Agents\Catalog as Agents;
use JesseGall\CodeCommandments\Agents\Instructions;
use JesseGall\CodeCommandments\Agents\SkillLink;
use JesseGall\CodeCommandments\Skills\Briefing;
use JesseGall\CodeCommandments\Config;
use JesseGall\CodeCommandments\Languages;
use JesseGall\CodeCommandments\Skills\Library;

use JesseGall\CodeCommandments\Cli\Plan\ChecksInference;
use JesseGall\CodeCommandments\Cli\Config\ConfigFile;
use JesseGall\CodeCommandments\Cli\Config\ConfigScribe;
use JesseGall\CodeCommandments\Cli\Config\DisableMenu;
use JesseGall\CodeCommandments\Cli\Migration;
use JesseGall\CodeCommandments\Support\Directory;
use JesseGall\CodeCommandments\Support\File;
use JesseGall\CodeCommandments\Support\Lock;
use JesseGall\PhpTypes\Option;
/**
 * `commandments sync` — refresh the consumer's code-commandments integration on install and every
 * `composer update`. Publishes the skills into the project's library, points every {@see Agent} at
 * them, writes the shared `AGENTS.md` briefing, and refreshes the config surface. Idempotent, and
 * it knows nothing about any particular assistant: which folders exist is each agent's business.
 */
final class Sync implements Command
{
    /**
     * Lines the package USED to guarantee in a consumer's `.commandments/.gitignore`. A rule we retire is
     * still OURS: without saying so it reads as a line the project added and travels forward for ever, so
     * every consumer that ever synced keeps un-ignoring a folder nothing writes to any more. They are the
     * literal text once written, not the text derived from today's constants — what was retired cannot
     * change when a constant does.
     *
     * @var list<string>
     */
    private const array RETIRED_GITIGNORE_LINES = [
        "# A session's PLAN is tracked; everything else in its folder is this run's own state.",
        '!sessions/*/plan/',
        '!sessions/*/plan/**',
        '!orchestrator/',
        '!orchestrator/**',
    ];

    public function names(): array
    {
        return ['sync'];
    }

    public function help(): Help
    {
        return Help::of("Refresh this project's code-commandments integration — publish the skills into the library every agent reads, refresh the AGENTS.md briefing and the config surface, and wire each agent's own view of them.")
            ->form('sync', 'run it (idempotent; composer runs it for you on every install/update once `install` has wired it)');
    }

    public function run(Input $input): int
    {
        $consumer = ConsumerRoot::from(getcwd() ?: '.');

        if ($consumer === null) {
            return HelpScreen::usage($this, 'no composer.json at or above ' . getcwd() . ' — sync publishes into a project, and would otherwise write into whatever directory you happen to be standing in.');
        }

        $lock = $this->lock($consumer);
        $packageRoot = dirname(__DIR__, 2);
        $agents = Agents::forProject($consumer);

        // The ignore rules go in FIRST. They are pure and idempotent, and everything after this
        // writes generated files into the project — a run that fails halfway (a project's own
        // config.php can fatal while its skills are being rendered) should not leave a tree of them
        // showing up as untracked.
        $this->ensureGitignored("{$consumer}/.gitignore", $agents);

        $library = new Library(Workspace::at($consumer), Languages::from(Config::load()));
        $published = $library->publish($packageRoot);

        // And the canon is written BEFORE anything points at it: an agent's own file is rewritten to
        // import `AGENTS.md`, and a failure between the two would leave it importing a file that
        // isn't there — which agents ignore in silence, so the briefing would simply be gone.
        $this->injectCanon($consumer);
        $this->wireAgents($consumer, $packageRoot, $agents, $library, $published);

        $this->ensureComposerHook($consumer);
        $this->ensureConfigStub($consumer);
        $this->ensurePlanExecution($consumer);
        $this->ensureDisableMenus($consumer);
        $this->ensureCommandmentsGitignore($consumer);
        $this->readWhichRulesReadBeyondOneFile($consumer);
        $this->removeLegacyArtifacts($consumer);
        $this->migrateState($consumer);

        $names = implode(', ', array_map(static fn (Agent $agent): string => $agent->name(), $agents));

        fwrite(STDOUT, '↻ code-commandments synced — ' . count($published) . " skills published to " . Workspace::LIBRARY . ", read by {$names}.\n");

        foreach ($lock as $held) {
            $held->release();
        }

        return 0;
    }

    /**
     * Hold the project's sync lock for the length of the run. A sync clears the published skills and
     * writes them back; two at once (a CI matrix, a second worktree, an editor firing `composer install`
     * under a manual one) means one deleting what the other just wrote. A hold that cannot be taken at
     * all is absent rather than refused — here the lock is a courtesy, not a permission.
     *
     * @return Option<Lock>
     */
    private function lock(string $consumer): Option
    {
        return Lock::on(Workspace::at($consumer)->shared('.sync.lock'));
    }

    /**
     * Re-assert the composer call that runs this command ({@see ComposerScripts}). It is the one
     * piece of wiring that cannot repair itself — while it is absent or points at a path this
     * project doesn't have, no upgrade runs us, so no upgrade can notice.
     */
    private function ensureComposerHook(string $consumer): void
    {
        if (new ComposerScripts($consumer)->ensure() === true) {
            fwrite(STDOUT, "↻ composer post-install/post-update now call `commandments sync`.\n");
        }
    }

    /**
     * Drop the commented-out `.commandments/config.php` scaffold the FIRST time we sync a
     * consumer, so the config surface is discoverable in place — nothing is enabled by it. Written
     * once and never touched again ({@see ConfigFile::scaffoldIfMissing}), so a project's real
     * edits are safe; delete it if you don't want one.
     */
    private function ensureConfigStub(string $consumer): void
    {
        ConfigFile::inProject($consumer)->scaffoldIfMissing();
    }

    /**
     * Self-heal the plan-execution surface into the consumer's config: a `$config->planExecution(...)`
     * block, its `onComplete` gate inferred from the project's own composer/npm scripts. A no-op once
     * the config already declares one (see {@see ConfigScribe::ensurePlanExecution}), so a project's
     * edits survive every `composer update`.
     */
    private function ensurePlanExecution(string $consumer): void
    {
        ConfigScribe::inProject($consumer)->ensurePlanExecution(ChecksInference::detect($consumer));
    }

    /**
     * Self-heal the disable menus into the consumer's config: every skill and sin as a
     * commented-out `disable()` argument. New entries are appended on every sync; the human's
     * lines are never rewritten (see {@see DisableMenu}).
     */
    private function ensureDisableMenus(string $consumer): void
    {
        DisableMenu::inProject($consumer)->ensure();
    }

    /**
     * Work out now which rules read past the file they judge ({@see CrossFileSet}) — a whole-package
     * parse, so it is done HERE, at the one moment a project is already waiting, and never on the
     * per-edit check that reads the answer back.
     */
    private function readWhichRulesReadBeyondOneFile(string $consumer): void
    {
        $configured = Config::load($consumer)->apply(Catalog::backend(), Catalog::frontend());

        CrossFileSet::reread(Workspace::at($consumer), [...$configured['backend'], ...$configured['frontend']]);
    }

    /**
     * The `.commandments/` folder carries its OWN `.gitignore`: ignore everything generated in here
     * (the checklist, archives, tool-use counter), keeping the DURABLE things tracked — the
     * `config.php`, the ignore file itself and the project's own `custom/` rules, which are source
     * and belong in the repo like any other.
     * Self-contained — nothing about the folder leaks into the project's root `.gitignore`.
     * Idempotent.
     *
     * A project's OWN lines are kept. This file is seeded by us and then edited by them, so
     * re-asserting our version over it un-tracked whatever they had added — silently, since the
     * files stay on disk and nothing breaks until somebody clones the repo.
     */
    private function ensureCommandmentsGitignore(string $consumer): void
    {
        $path = Workspace::at($consumer)->shared('.gitignore');
        $content = implode("\n", [...self::gitignoreLines(), ...$this->linesTheProjectAdded($path)]) . "\n";

        if (! is_file($path) || (string) file_get_contents($path) !== $content) {
            @mkdir(dirname($path), 0777, true);
            File::write($path, $content);
        }
    }

    /**
     * The lines the package guarantees. Un-ignoring a directory takes BOTH forms: `!custom/`
     * re-admits the directory (git never descends into an ignored one) and `!custom/**` re-admits
     * everything inside it.
     *
     * @return list<string>
     */
    private static function gitignoreLines(): array
    {
        $durable = [Workspace::CUSTOM];

        return [
            '# code-commandments generated state; the lines below stay tracked.',
            '# Add your own exceptions underneath — they are preserved across a sync.',
            '*',
            '!.gitignore',
            '!config.php',
            ...array_merge(...array_map(fn (string $folder) => ["!{$folder}/", "!{$folder}/**"], $durable)),
            ...self::sessionTaskLines(),
        ];
    }

    /**
     * A session holds its own tasks, which makes the session the thing you come BACK to — so `tasks/` is
     * tracked while everything else in it is not. Its three folders ARE the state of the work, so a diff
     * shows what moved; the board, the journal and the counters are this run's own and stay ignored.
     *
     * Un-ignoring something nested takes a line per level, because git never descends into an ignored
     * directory to find an exception inside it.
     *
     * @return list<string>
     */
    private static function sessionTaskLines(): array
    {
        $sessions = Workspace::SESSIONS;

        return [
            '',
            "# A session's TASKS are tracked; everything else in its folder is this run's own state.",
            '# Un-ignoring something nested takes a line per level, so these four are one rule.',
            "!{$sessions}/",
            "!{$sessions}/*/",
            "!{$sessions}/*/tasks/",
            "!{$sessions}/*/tasks/**",
        ];
    }

    /**
     * Whatever the project wrote into the file that is not ours — kept verbatim, in their order. Our own
     * past headers and {@see RETIRED_GITIGNORE_LINES} are dropped rather than travelling forward for ever;
     * any other comment is theirs and stays.
     *
     * @return list<string>
     */
    private function linesTheProjectAdded(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $ours = self::gitignoreLines();
        $theirs = [];

        foreach (explode("\n", (string) file_get_contents($path)) as $line) {
            $kept = trim($line);

            if ($kept === '' || self::isOurs($kept, $ours)) {
                continue;
            }

            $theirs[] = $kept;
        }

        return $theirs;
    }

    /**
     * Is $line one the PACKAGE wrote — a rule it guarantees today, one it has since retired, or one of its
     * own headers? Everything else in the file is the project's, and stays.
     *
     * @param  list<string>  $ours  the lines this version guarantees
     */
    private static function isOurs(string $line, array $ours): bool
    {
        return in_array($line, $ours, true)
            || in_array($line, self::RETIRED_GITIGNORE_LINES, true)
            || str_starts_with($line, '# code-commandments');
    }

    /**
     * Bring the project's session state up to the current file FORMAT — once, on the sync that follows
     * the upgrade ({@see Migration}). Deleting these files would be simpler, but the stop gate's
     * conditions and a plan's constraints are things the USER asked for; an upgrade must not throw
     * away instructions the agent is meant to be held to. Silent when there is nothing to convert.
     */
    private function migrateState(string $consumer): void
    {
        $converted = new Migration(Workspace::at($consumer))->run();

        if ($converted !== []) {
            fwrite(STDOUT, '↻ session state upgraded — carried over ' . implode(', ', $converted) . ".\n");
        }
    }

    /**
     * Delete artifacts at their legacy locations — the root checklist, and the flat `.commandments/`
     * state files whose live home is the session folder (`.commandments/sessions/<key>/`). Runs on
     * every sync so a consumer migrates automatically on `composer update`; the files are
     * generated/gitignored, so removing them is always safe (each one regenerates).
     */
    private function removeLegacyArtifacts(string $consumer): void
    {
        // A literal list on purpose: these names must stay frozen even when the live layout
        // (which Workspace owns) changes again — they exist only to be deleted.
        $flat = [
            'sins.md', '.plan-active', '.plan-stuck', '.plan-working-state', '.plan-working-state.previous',
            '.plan-constraints', '.constraints-verified', '.plan-testing', '.judge-reminded', '.remind-checklist',
        ];

        $legacy = [
            'commandments-sins.md',
            ...array_map(static fn (string $file): string => ".commandments/{$file}", $flat),
        ];

        foreach ($legacy as $file) {
            $path = "{$consumer}/{$file}";

            if (is_file($path)) {
                @unlink($path);
            }
        }

        foreach ([...glob("{$consumer}/.commandments/sins-*.md") ?: [], ...glob("{$consumer}/.commandments/.*-count") ?: []] as $path) {
            @unlink($path);
        }
    }

    /**
     * Everything the package GENERATES is regenerated, not hand-authored — so the library and every
     * agent's view of it are ignored. Each agent states its own entries; the library's is stated
     * here, because it belongs to no one agent. Re-asserted on every sync, idempotent.
     *
     * @param  list<Agent>  $agents
     */
    private function ensureGitignored(string $path, array $agents): void
    {
        $existing = is_file($path) ? (string) file_get_contents($path) : '';

        // Rule forms we have retired, removed by exact line so a rule the USER wrote is never taken
        // for one of ours. The `.commandments/` family moved into that folder's own ignore file. The
        // nested `.claude/skills/commandments/` never matched anything. And the TRAILING SLASH on
        // the published-skills rule now actively fails: a slash means "directory only", and git
        // records a symlink as a file, so once the published skills became links that rule stopped
        // ignoring them. It has to go rather than sit alongside the corrected form, because the
        // check below is a substring one — the old line would answer for the new.
        $stale = [
            '.commandments/', '.commandments/*', '!.commandments/config.php', '!.commandments/repent.php',
            '.claude/skills/commandments/', '.claude/skills/commandments-*/',
        ];
        $existing = implode("\n", array_filter(
            explode("\n", $existing),
            static fn (string $line): bool => ! in_array(trim($line), $stale, true),
        ));

        $entries = ['# code-commandments skill library (regenerated on composer update)' => Workspace::LIBRARY . '/commandments-*/'];

        foreach ($agents as $agent) {
            $entries = [...$entries, ...$agent->ignored()];
        }

        foreach ($entries as $comment => $entry) {
            if (str_contains($existing, $entry)) {
                continue;
            }

            $prefix = ($existing !== '' && ! str_ends_with($existing, "\n")) ? "\n" : '';
            $existing .= $prefix . "\n{$comment}\n{$entry}\n";
        }

        if ($existing !== '') {
            File::write($path, $existing);
        }
    }

    /**
     * The canon briefing — the one document EVERY agent is meant to read, in the file most of them
     * already do. An agent that cannot read it gets a file of its own that imports this one, so the
     * disciplines are written down once however many assistants a project uses.
     */
    private function injectCanon(string $consumer): void
    {
        new Instructions("{$consumer}/AGENTS.md", $consumer)->inject(Briefing::BLOCK, Briefing::render($consumer));
    }

    /**
     * Point each agent at what it reads: a link per skill into the library, the commands it offers a
     * human, its own instructions file when it cannot read the canon, and whatever else it wires for
     * itself. An agent whose folder IS the library needs none of it.
     *
     * @param  list<Agent>  $agents
     * @param  list<string>  $published
     */
    private function wireAgents(string $consumer, string $packageRoot, array $agents, Library $library, array $published): void
    {
        $link = new SkillLink();
        $canon = new Instructions("{$consumer}/AGENTS.md", $consumer);

        foreach ($agents as $agent) {
            if (($skills = $agent->skillsDir()) !== null) {
                // The ancient nested scheme, from before skills were published flat. It belongs to
                // the agent whose folder it is in, not to the library.
                Directory::delete("{$consumer}/{$skills}/commandments");

                foreach ($published as $id) {
                    $link->point("{$consumer}/{$skills}/{$id}", $library->path($id));
                }

                $library->reconcile("{$consumer}/{$skills}", $published);
            }

            if (($commands = $agent->commandsDir()) !== null) {
                $this->publishCommands("{$packageRoot}/commands", "{$consumer}/{$commands}");
            }

            $this->injectAgentInstructions($consumer, $agent, $canon);

            $agent->wire($consumer);
        }
    }

    /**
     * An agent's own instructions file, for the one that cannot read the canon. Skipped when it
     * turns out to BE the canon — a project may well have made `CLAUDE.md` a link to `AGENTS.md`,
     * and writing both would put two blocks in one file.
     */
    private function injectAgentInstructions(string $consumer, Agent $agent, Instructions $canon): void
    {
        $file = $agent->instructionsFile();

        if ($file === null) {
            return;
        }

        $instructions = new Instructions("{$consumer}/{$file}", $consumer);

        if (! $instructions->isSameFileAs($canon)) {
            $instructions->inject($agent->blockName(), $agent->instructions());
        }
    }

    /**
     * Publish the package's commands into an agent's command folder — the HUMAN's handle on the
     * agent-facing verbs (currently `/until "<condition>"`). A skill teaches the agent; a command
     * lets the user fire it in one line.
     */
    private function publishCommands(string $source, string $target): void
    {
        foreach (glob("{$source}/*.md") ?: [] as $command) {
            @mkdir($target, 0775, true);
            File::write($target . '/' . basename($command), (string) file_get_contents($command));
        }
    }
}
