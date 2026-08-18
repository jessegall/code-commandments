<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;


use JesseGall\CodeCommandments\Cli\Help\Help;
use JesseGall\CodeCommandments\Cli\Help\HelpScreen;
use JesseGall\CodeCommandments\Hooks\HookRegistry;
/**
 * `commandments install` — wire a consumer up once. Adds a `commandments sync`
 * call to the consumer's composer `post-update-cmd` and `post-install-cmd` so the
 * skills + CLAUDE.md briefing refresh automatically on every `composer
 * update`/`install`, wires our Claude Code hooks ({@see Hooks} — the cardinal-rule
 * heartbeat and the "did you judge?" nudge), then runs an initial sync. Idempotent.
 */
final class Install implements Command
{
    public function names(): array
    {
        return ['install'];
    }

    public function help(): Help
    {
        return Help::of('Wire a consumer project up once — the composer sync hook, every agent it supports (skills, AGENTS.md, and under Claude Code the hook suite) and .gitignore — then sync.')
            ->form('install', 'wire it (idempotent; every hook we write is stamped, so your own hooks are never touched)');
    }

    public function run(Input $input): int
    {
        $consumer = ConsumerRoot::from(getcwd() ?: '.');

        if ($consumer === null) {
            return HelpScreen::usage($this, 'no composer.json at or above ' . getcwd() . ' — there is no project here to wire.');
        }

        $wired = new ComposerScripts($consumer)->ensure();

        if ($wired === null) {
            return HelpScreen::usage($this, "{$consumer}/composer.json is not readable JSON — fix it first; rewriting it from here would throw away everything it declares.");
        }

        $hooked = HookRegistry::wire($consumer);

        fwrite(STDOUT, $wired
            ? "✓ Wired `commandments sync` into composer post-update-cmd / post-install-cmd.\n"
            : "✓ composer hooks already wired.\n");

        fwrite(STDOUT, $hooked
            ? "✓ Wired the Claude Code hooks: the cardinal-rule reminder (PostToolUse) + the judge nudge (Stop).\n"
            : "✓ Claude Code hooks already wired.\n");

        return (new Sync)->run($input);
    }

}
