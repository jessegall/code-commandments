<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use JesseGall\CodeCommandments\Cli\Help\Help;
use JesseGall\CodeCommandments\Cli\Help\HelpScreen;
use JesseGall\CodeCommandments\Workspace;

use JesseGall\CodeCommandments\Custom;
use JesseGall\CodeCommandments\Skills\ClaudeSection;
use JesseGall\CodeCommandments\Skills\SkillRenderer;
use JesseGall\CodeCommandments\Skills\Catalog as Skills;

use JesseGall\CodeCommandments\Hooks\HookRegistry;
use JesseGall\CodeCommandments\Cli\Plan\ChecksInference;
use JesseGall\CodeCommandments\Cli\Config\ConfigFile;
use JesseGall\CodeCommandments\Cli\Config\ConfigScribe;
use JesseGall\CodeCommandments\Cli\Config\DisableMenu;
use JesseGall\CodeCommandments\Cli\State\Migration;
use JesseGall\CodeCommandments\Support\Directory;
use JesseGall\CodeCommandments\Support\File;
/**
 * `commandments sync` — refresh the consumer's code-commandments integration so a
 * Publishes the current skills, CLAUDE.md briefing, config surface, and Claude Code hooks
 * into the consumer on install and every `composer update`. Idempotent; runs in the
 * consumer's working directory.
 */
final class Sync implements Command
{
    /**
     * The standalone, hand-written skills — NOT the {@see Skills\Catalog} teaching skills projected
     * from sins, but process skills that ship as-is (`executing-plans`, `until`,
     * `writing-detectors`). Each is published flat under `.claude/skills/commandments-<slug>/`, the
     * same convention the teaching skills use.
     */
    private const array STANDALONE = ['executing-plans', 'until', 'writing-detectors'];

    public function names(): array
    {
        return ['sync'];
    }

    public function help(): Help
    {
        return Help::of("Refresh this project's code-commandments integration — publish the skills, refresh the CLAUDE.md briefing and the config surface, and wire the Claude Code hooks.")
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

        $published = $this->publishSkills("{$packageRoot}/skills/commandments", $consumer)
            + $this->publishStandaloneSkills("{$packageRoot}/skills", $consumer)
            + $this->publishCustomSkills($consumer);
        $this->publishCommands("{$packageRoot}/commands", $consumer);
        $this->injectClaudeSection($consumer);
        $this->ensureGitignored("{$consumer}/.gitignore");
        $this->ensureConfigStub($consumer);
        $this->ensurePlanExecution($consumer);
        $this->ensureDisableMenus($consumer);
        $this->ensureCommandmentsGitignore($consumer);
        HookRegistry::wire("{$consumer}/.claude/settings.json", HookRegistry::forProject($consumer));
        $this->removeLegacyArtifacts($consumer);
        $this->migrateState($consumer);

        fwrite(STDOUT, "↻ code-commandments synced — {$published} skills published, CLAUDE.md briefing refreshed.\n");

        $this->unlock($lock);

        return 0;
    }

    /**
     * Hold the project's sync lock for the length of the run, waiting for another one to finish
     * rather than interleaving with it. A sync clears the published skills and writes them back; two
     * at once (a CI matrix, a second worktree, an editor firing `composer install` under a manual
     * one) means one deleting what the other just wrote. Null when the lock cannot be taken at all,
     * which is not worth refusing over — the lock is a courtesy, not a permission.
     *
     * @return ?resource
     */
    private function lock(string $consumer)
    {
        $path = Workspace::at($consumer)->shared('.sync.lock');

        @mkdir(dirname($path), 0775, true);

        $handle = @fopen($path, 'c');

        if ($handle === false) {
            return null;
        }

        flock($handle, LOCK_EX);

        return $handle;
    }

    /**
     * @param  ?resource  $lock
     */
    private function unlock($lock): void
    {
        if ($lock !== null) {
            flock($lock, LOCK_UN);
            fclose($lock);
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
     * The `.commandments/` folder carries its OWN `.gitignore`: ignore everything generated in here
     * (the checklist, archives, tool-use counter), keeping the HAND-WRITTEN things tracked — the
     * `config.php`, the ignore file itself, and the project's own `custom/` rules
     * ({@see Workspace::CUSTOM}), which are source code and belong in the repo like any other.
     * Self-contained — nothing about the folder leaks into the project's root `.gitignore`.
     * Idempotent.
     */
    private function ensureCommandmentsGitignore(string $consumer): void
    {
        $path = Workspace::at($consumer)->shared('.gitignore');
        $custom = Workspace::CUSTOM;
        // Un-ignoring a directory takes BOTH lines: `!custom/` re-admits the directory (git never
        // descends into an ignored one) and `!custom/**` re-admits everything inside it.
        $content = "# code-commandments generated state; config.php and custom/ stay tracked\n*\n!.gitignore\n!config.php\n!{$custom}/\n!{$custom}/**\n";

        if (! is_file($path) || (string) file_get_contents($path) !== $content) {
            @mkdir(dirname($path), 0777, true);
            File::write($path, $content);
        }
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
     * Everything the package GENERATES is regenerated, not hand-authored — the judge
     * checklist and any future state live under `.commandments/`, so its contents are
     * ignored. The ONE exception is `.commandments/config.php`, the project's own
     * hand-written config, which stays tracked. The published skills must sit in
     * `.claude/skills/` for the Skill tool to find them, so they're ignored there.
     * Re-asserted on every sync so consumers pick it up on `composer update`. Idempotent.
     */
    private function ensureGitignored(string $path): void
    {
        $existing = is_file($path) ? (string) file_get_contents($path) : '';

        // Strip earlier RULE forms — the folder now carries its OWN `.commandments/.gitignore`
        // (see {@see ensureCommandmentsGitignore}), so its rules no longer belong in the root: a
        // bare `.commandments/`, the `.commandments/*` + `!config.php` pair, and the old
        // `!repent.php` negation are all migrated out. Comments are left as-is (harmless).
        // Also migrate the earlier published-skills rule `.claude/skills/commandments/` — it never
        // matched the FLAT `commandments-*` dirs skills actually publish to; the glob below does.
        $stale = ['.commandments/', '.commandments/*', '!.commandments/config.php', '!.commandments/repent.php', '.claude/skills/commandments/'];
        $existing = implode("\n", array_filter(
            explode("\n", $existing),
            static fn (string $line): bool => ! in_array(trim($line), $stale, true),
        ));

        $entries = [
            // Every published skill lives OUTSIDE `.commandments/` (flat under `.claude/skills/
            // commandments-*/`), so this glob is the one root entry the package still manages.
            '# code-commandments published skills (regenerated on composer update)' => '.claude/skills/commandments-*/',
            // The slash commands are published the same way and for the same reason: generated, never
            // hand-edited — a consumer's own commands in that folder stay tracked.
            '# code-commandments published slash commands (regenerated on composer update)' => '.claude/commands/until.md',
        ];

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

    private function publishSkills(string $source, string $consumer): int
    {
        $this->removeLegacySkills($consumer);

        $count = 0;

        foreach (Skills::all() as $skill) {
            $from = "{$source}/{$skill->slug}";
            // FLAT, one level deep — Claude Code discovers `.claude/skills/<id>/SKILL.md`
            // and the directory name IS the Skill-tool invocation (`commandments-backend-absence`).
            // A nested `commandments/backend/absence/` is never found.
            $to = "{$consumer}/.claude/skills/{$skill->id()}";

            if (is_dir($from)) {
                Directory::copy($from, $to);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Publish the package's slash commands into `.claude/commands/` — the HUMAN's handle on the
     * agent-facing verbs (currently `/until "<condition>"`). A skill teaches the agent; a command
     * lets the user fire it in one line. Regenerated on every sync like the skills, so a consumer
     * picks up new commands on `composer update`.
     */
    private function publishCommands(string $source, string $consumer): void
    {
        foreach (glob("{$source}/*.md") ?: [] as $command) {
            $to = "{$consumer}/.claude/commands/" . basename($command);

            @mkdir(dirname($to), 0777, true);
            copy($command, $to);
        }
    }

    /**
     * Publish the PROJECT's own skills — the {@see Skill} classes it wrote into
     * `.commandments/custom/` ({@see Custom::skills}) — rendered by the SAME
     * {@see SkillRenderer} that generates the shipped ones, into the same flat
     * `.claude/skills/commandments-<slug>/` home. There is no second renderer and no second
     * publishing rule: a project's rule points at a skill the agent can actually load, exactly
     * as a shipped rule does. Regenerated every sync, so editing the Skill class is how the
     * document changes — never the markdown.
     */
    private function publishCustomSkills(string $consumer): int
    {
        $renderer = new SkillRenderer();
        $count = 0;

        foreach (Custom::skills($consumer) as $skill) {
            $dir = "{$consumer}/.claude/skills/{$skill->id()}";

            @mkdir($dir, 0775, true);
            File::write("{$dir}/SKILL.md", $renderer->render($skill));
            $count++;
        }

        return $count;
    }

    private function publishStandaloneSkills(string $source, string $consumer): int
    {
        $count = 0;

        foreach (self::STANDALONE as $slug) {
            $from = "{$source}/{$slug}";
            $to = "{$consumer}/.claude/skills/commandments-{$slug}";

            if (is_dir($from)) {
                Directory::copy($from, $to);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Clear previously-published skills before republishing the current flat set: the
     * broken NESTED scheme (`.claude/skills/commandments/`) and any stale flat
     * `commandments-*` dirs (so a renamed/dropped skill doesn't linger). Published skills
     * are regenerated and gitignored, so deleting them is always safe.
     */
    private function removeLegacySkills(string $consumer): void
    {
        if (is_dir("{$consumer}/.claude/skills/commandments")) {
            Directory::delete("{$consumer}/.claude/skills/commandments");
        }

        foreach (glob("{$consumer}/.claude/skills/commandments-*", GLOB_ONLYDIR) ?: [] as $stale) {
            Directory::delete($stale);
        }
    }

    private function injectClaudeSection(string $consumer): void
    {
        $path = "{$consumer}/CLAUDE.md";
        $block = ClaudeSection::render($consumer);
        $existing = is_file($path) ? (string) file_get_contents($path) : '';

        $begin = strpos($existing, ClaudeSection::BEGIN);
        $end = strpos($existing, ClaudeSection::END);

        if ($begin !== false && $end !== false) {
            $updated = substr($existing, 0, $begin) . $block . substr($existing, $end + strlen(ClaudeSection::END));
        } elseif ($existing === '') {
            $updated = "# CLAUDE.md\n\n{$block}\n";
        } else {
            // Insert the block right after the first heading/line.
            $split = strpos($existing, "\n");
            $head = $split === false ? $existing : substr($existing, 0, $split + 1);
            $rest = $split === false ? '' : substr($existing, $split + 1);
            $updated = $head . "\n" . $block . "\n" . $rest;
        }

        if ($updated !== $existing) {
            File::write($path, $updated);
        }
    }

}
