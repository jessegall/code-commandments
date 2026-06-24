<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Profiles;

use JesseGall\CodeCommandments\Support\ClaudeHooksInstaller;
use JesseGall\CodeCommandments\Support\ClaudeMdInstaller;
use JesseGall\CodeCommandments\Support\CommitHookInstaller;
use JesseGall\CodeCommandments\Support\Skills\SkillInstaller;
use JesseGall\CodeCommandments\Support\Skills\SkillRegistry;
use JesseGall\PhpTypes\T_String;

/**
 * The one orchestrator behind the `profile` command. Reads/writes the local,
 * gitignored selection (`.commandments/profile`), resolves the active profile
 * (with legacy back-compat inference), and APPLIES a profile — installing exactly
 * its git hooks, Claude hooks, briefing, and CLAUDE.md section while tearing down
 * whatever the previous profile left, computed from what is actually on disk.
 *
 * Switching never touches the confession tracker: absolutions are a commit/push
 * concern, not a profile concern.
 */
final class ProfileService
{
    public const SUCCESS = 0;
    public const FAILURE = 1;

    private const STATE = '.commandments/profile';
    private const BRIEFED = '.commandments/profile-last-briefed';

    private readonly string $basePath;

    /** @param array<string, mixed> $config the consumer's commandments config */
    public function __construct(string $basePath, private readonly array $config = [])
    {
        $this->basePath = rtrim($basePath, '/');
    }

    /**
     * The active profile: the stored selection, else `phased` when a legacy setup
     * is detected (back-compat — an existing consumer is never silently disabled),
     * else the default `disabled`.
     */
    public function active(): Profile
    {
        $name = $this->readState();

        if ($name !== null && ProfileRegistry::has($name)) {
            return ProfileRegistry::get($name);
        }

        // A linked git worktree shares the main repo's .git/hooks and may share a
        // committed CLAUDE.md, so neither is evidence THAT worktree opted into a
        // profile. Never infer one for it — it stays dormant (disabled) until a
        // profile is explicitly selected in the worktree (writing its own
        // .commandments/profile). This keeps worktrees isolated from each other.
        if (! $this->isLinkedWorktree() && $this->hasLegacySetup()) {
            return ProfileRegistry::get('phased');
        }

        return ProfileRegistry::default();
    }

    /**
     * Whether $basePath is a LINKED git worktree (its `.git` is a file pointing at
     * `…/.git/worktrees/<name>`), as opposed to the main checkout (`.git` is a dir).
     */
    private function isLinkedWorktree(): bool
    {
        return is_file($this->basePath . '/.git');
    }

    /**
     * Static convenience for the judge / hook read path (no config needed to
     * resolve scope and severity).
     */
    public static function resolve(string $basePath): Profile
    {
        return (new self($basePath))->active();
    }

    /**
     * The scope a bare `judge` should use — but ONLY from an EXPLICITLY-selected
     * profile (`.commandments/profile` present). Returns null when there is no
     * explicit selection, so a legacy consumer (inferred `phased` for hooks'
     * sake) keeps its historical full-scan `judge` until it opts into a profile.
     */
    public static function explicitScope(string $basePath): ?JudgeScope
    {
        $name = (new self($basePath))->readState();

        if ($name === null || ! ProfileRegistry::has($name)) {
            return null;
        }

        return ProfileRegistry::get($name)->options()->scope;
    }

    /**
     * Whether a bare/scoped `judge` should treat WARNINGS as blocking — but ONLY
     * from an EXPLICITLY-selected profile (mirrors {@see self::explicitScope}). A
     * legacy consumer (inferred `phased` for hooks' sake) keeps its historical
     * non-blocking-warnings `judge` until it opts into a profile.
     */
    public static function explicitGateBlocksOnWarnings(string $basePath): bool
    {
        $name = (new self($basePath))->readState();

        if ($name === null || ! ProfileRegistry::has($name)) {
            return false;
        }

        return ProfileRegistry::get($name)->options()->gateBlocksOnWarnings();
    }

    /**
     * @param  callable(string): void  $emit
     * @param  callable(string): void  $error
     */
    public function switch(string $name, callable $emit, callable $error): int
    {
        if (! ProfileRegistry::has($name)) {
            $error("Unknown profile: {$name}. Available: " . implode(', ', ProfileRegistry::names()));

            return self::FAILURE;
        }

        $new = ProfileRegistry::get($name);

        $this->apply($new, $emit, $error);
        $this->writeState($name);
        // Deliberately do NOT touch the briefed marker here: the drift hook must
        // still fire (re-index Claude) whether the switch ran inside this session
        // or from another terminal. The immediate re-brief below covers the former.
        $this->emitReindex($new, $emit);

        return self::SUCCESS;
    }

    /**
     * @param  callable(string): void  $emit
     */
    public function list(callable $emit): void
    {
        $active = $this->active()->name();

        $emit('Profiles (* = active):');
        $emit(T_String::empty());

        foreach (ProfileRegistry::all() as $name => $profile) {
            $marker = $name === $active ? '*' : ' ';
            $emit("{$marker} {$name} — {$profile->description()}");
        }
    }

    /**
     * @param  callable(string): void  $emit
     */
    public function show(callable $emit): void
    {
        $profile = $this->active();

        $emit("Active profile: {$profile->name()}");
        $emit("  {$profile->description()}");

        foreach ($this->contractSummary($profile) as $line) {
            $emit("  {$line}");
        }
    }

    /**
     * `--brief`: the SessionStart briefing for the active profile. Adopts the
     * current profile as the briefed baseline so the per-turn drift hook stays quiet
     * until the profile actually changes.
     *
     * @param  callable(string): void  $emit
     */
    public function brief(callable $emit): void
    {
        $profile = $this->active();

        if (! $profile->options()->briefAgent) {
            $this->writeBriefed($profile->name());

            return;
        }

        foreach ($this->briefingLines($profile) as $line) {
            $emit($line);
        }

        $this->writeBriefed($profile->name());
    }

    /**
     * `--drift-check`: re-assert the active profile's contract EVERY turn so the
     * agent can't quietly drift back to its trained defaults (per-phase gating,
     * verifying each step, etc.) as the session-start briefing decays. A profile
     * *change* (this session, a hand-edit, a teammate's merge) gets the louder
     * "discard the previous contract" framing; an unchanged profile gets a terse
     * one-line reminder of the same contract. Silent only for dormant profiles.
     *
     * @param  callable(string): void  $emit
     */
    public function driftCheck(callable $emit): void
    {
        $profile = $this->active();
        $current = $profile->name();
        $changed = $this->readBriefed() !== $current;

        if (! $profile->options()->briefAgent) {
            // A dormant profile (no briefing) — nothing to say, just sync the marker.
            $this->writeBriefed($current);

            return;
        }

        $emit($changed
            ? "[code-commandments] The active profile is now \"{$current}\". Discard any previous commandments contract and follow this:"
            : "[code-commandments] Profile \"{$current}\" still active — your standing contract this turn (do NOT drift back to per-phase gating or any default habit it overrides):");

        foreach ($this->contractSummary($profile) as $line) {
            $emit("  {$line}");
        }

        $this->writeBriefed($current);
    }

    /**
     * Re-assert the ACTIVE profile's bundle on a package update (called by `sync`).
     * Persists the inferred selection so a legacy consumer becomes explicitly
     * `phased`, then refreshes that profile's hooks/wiring/docs to the current
     * version — using REPLACE-ONLY semantics for the docs (never force-feeds a
     * CLAUDE.md section or a settings.json onto a consumer that doesn't have one).
     * For a greenfield/`disabled` consumer this removes nothing.
     *
     * @param  callable(string): void  $emit
     * @param  callable(string): void  $error
     */
    public function reassert(callable $emit, callable $error): void
    {
        $profile = $this->active();
        $opts = $profile->options();

        if ($this->readState() === null) {
            $this->writeState($profile->name());
            $emit("Recorded the active code-commandments profile as \"{$profile->name()}\".");
        }

        // Git hooks: reconcile to the profile's set (refresh bodies, drop stale
        // blocks). Quiet when already correct.
        (new CommitHookInstaller())->applyBlocks($this->basePath, $this->desiredBlocks($opts), $emit, $error);

        // Claude hooks: refresh ONLY when the consumer already has a settings.json
        // (a routine sync must not impose hooks on a project that never opted in).
        if (is_file($this->basePath . '/.claude/settings.json')
            && ClaudeHooksInstaller::writeForProfile($this->basePath, $opts, $this->planLoopEnabled()) === ClaudeHooksInstaller::STATUS_INSTALLED) {
            $emit('Refreshed the Claude hook wiring in .claude/settings.json');
        }

        // CLAUDE.md cleanup on update: briefing is hook-delivered now, so strip any
        // legacy `## Code Commandments` section a previous package version left in
        // the consumer's committed CLAUDE.md. Runs after the inferred state is
        // persisted above, so a legacy consumer is already resolved to `phased`
        // before its CLAUDE.md marker (one of the legacy signals) is removed.
        if (ClaudeMdInstaller::remove($this->basePath) === ClaudeMdInstaller::STATUS_REMOVED) {
            $emit('Cleaned the legacy Code Commandments section from CLAUDE.md (briefing is now hook-delivered)');
        }
    }

    /**
     * Install the new profile's bundle and strip what it no longer owns.
     *
     * @param  callable(string): void  $emit
     * @param  callable(string): void  $error
     */
    private function apply(Profile $new, callable $emit, callable $error): void
    {
        $opts = $new->options();

        (new CommitHookInstaller())->applyBlocks($this->basePath, $this->desiredBlocks($opts), $emit, $error);

        match (ClaudeHooksInstaller::writeForProfile($this->basePath, $opts, $this->planLoopEnabled())) {
            ClaudeHooksInstaller::STATUS_INSTALLED => $emit('Updated .claude/settings.json hooks for this profile'),
            ClaudeHooksInstaller::STATUS_WRITE_FAILED => $error('Failed to write .claude/settings.json — check permissions.'),
            default => null,
        };

        // The `/commandments-profile` skill is the entry point to switch profiles,
        // so it stays installed even under `disabled` (you still need a way back on).
        $this->ensureProfileSkill();

        // Generate THIS profile's Stop hook onto the fixed .claude/hooks/stop-hook.sh
        // from its behaviour — switching between active profiles just regenerates
        // the file, so the settings entry never changes. A profile with no Stop
        // hook (disabled) removes the script.
        if ($opts->hasStopHook()) {
            StopHookInstaller::install($this->basePath, $new->name(), $opts);
        } else {
            @unlink($this->basePath . '/.claude/hooks/' . StopHookInstaller::INSTALLED_NAME);
        }

        // Migration: delete the retired keep-going scripts that the per-profile
        // stop-hook.sh replaced (#197), so a consumer updated from an older
        // package version is not left with confusing dead hook files.
        foreach (ClaudeHooksInstaller::RETIRED_SCRIPTS as $retired) {
            @unlink($this->basePath . '/.claude/hooks/' . $retired);
        }

        // Briefing is delivered by the local session-start hook (scripture /
        // `profile --brief`), NOT committed CLAUDE.md — so strip any legacy
        // `## Code Commandments` section for EVERY profile. This keeps CLAUDE.md
        // free of commandments knowledge (per-dev profiles never touch a shared,
        // committed file) and cleans up sections left by older package versions.
        match (ClaudeMdInstaller::remove($this->basePath)) {
            ClaudeMdInstaller::STATUS_REMOVED => $emit('Removed the legacy Code Commandments section from CLAUDE.md (briefing now comes from the session-start hook)'),
            ClaudeMdInstaller::STATUS_SKIPPED_CONFLICT => $error('CLAUDE.md has merge conflict markers — left it untouched.'),
            ClaudeMdInstaller::STATUS_WRITE_FAILED => $error('Failed to write CLAUDE.md — check permissions.'),
            default => null,
        };
    }

    /**
     * The git hook blocks a profile owns, derived from its options.
     *
     * @return list<string>
     */
    private function desiredBlocks(ProfileOptions $opts): array
    {
        $blocks = [];

        if (GitGateStage::PreCommit->equals($opts->gate)) {
            $blocks[] = CommitHookInstaller::BLOCK_PRE_COMMIT_GATE;
        }

        if (GitGateStage::PrePush->equals($opts->gate)) {
            $blocks[] = CommitHookInstaller::BLOCK_PRE_PUSH_GATE;
        }

        if ($opts->postCommitReset) {
            $blocks[] = CommitHookInstaller::BLOCK_POST_COMMIT_RESET;
        }

        if ($opts->prePushReset) {
            $blocks[] = CommitHookInstaller::BLOCK_PRE_PUSH_RESET;
        }

        // The commit-msg guard rides along whenever the package is active at all.
        if ($opts->briefAgent || GitGateStage::None->notEquals($opts->gate) || $opts->perPhaseNudges) {
            $blocks[] = CommitHookInstaller::BLOCK_COMMIT_MSG_GUARD;
        }

        return $blocks;
    }

    /**
     * Install the `commandments-profile` skill (only) — the always-available entry
     * point for switching profiles. Never clobbers an existing copy.
     */
    private function ensureProfileSkill(): void
    {
        $namespace = is_array($this->config['scaffold'] ?? null)
            ? T_String::coalesce($this->config['scaffold']['namespace'] ?? null, 'App\\Support')
            : 'App\\Support';

        $except = [];
        foreach (SkillRegistry::all() as $skill) {
            if ($skill->slug !== 'profile') {
                $except[] = $skill->slug;
            }
        }

        SkillInstaller::packaged()->install($namespace, $this->basePath . '/.claude/skills', false, $except, false);
    }

    private function planLoopEnabled(): bool
    {
        $hooks = $this->config['hooks'] ?? [];

        return is_array($hooks) && (bool) ($hooks['plan_loop'] ?? false);
    }

    /**
     * Whether the consumer already ran the legacy install (so absent state must
     * resolve to `phased`, never `disabled`).
     */
    private function hasLegacySetup(): bool
    {
        if ((new CommitHookInstaller())->installedBlocks($this->basePath) !== []) {
            return true;
        }

        $claudeMd = $this->basePath . '/CLAUDE.md';

        if (! is_file($claudeMd)) {
            return false;
        }

        $content = (string) @file_get_contents($claudeMd);

        return str_contains($content, ClaudeMdInstaller::BEGIN)
            || preg_match('/^## Code Commandments\b/m', $content) === 1;
    }

    /**
     * @param  callable(string): void  $emit
     */
    private function emitReindex(Profile $new, callable $emit): void
    {
        $emit(T_String::empty());
        $emit("Switched to the \"{$new->name()}\" profile.");

        foreach ($this->contractSummary($new) as $line) {
            $emit("  {$line}");
        }

        if ($new->options()->briefAgent) {
            $runner = ClaudeHooksInstaller::runnerFor($this->basePath);
            $r = $runner[0] . $runner[1];

            $emit('(Claude: discard any previous commandments contract and follow the above from now on.)');
            $emit(T_String::empty());
            $emit('BEFORE you plan or write code, LOAD the commandments knowledge so your plan respects it:');
            $emit("  {$r}scripture            # the rules");
            $emit("  {$r}skills               # the architectural how-to skills (read the ones your work touches)");
            $emit('A plan written without this knowledge will fight the prophets — load it first.');
        }
    }

    /**
     * A concise, options-derived contract summary — the mid-session re-brief and
     * the body of `show`. Compaction-proof: self-sufficient without a scripture trip.
     *
     * @return list<string>
     */
    private function contractSummary(Profile $profile): array
    {
        $o = $profile->options();

        $scope = match ($o->scope) {
            JudgeScope::None => 'the full codebase',
            JudgeScope::Staged => 'your staged changes',
            JudgeScope::Branch => "the whole branch's changes",
        };

        $isPenance = GitGateStage::PrePush->equals($o->gate) && JudgeScope::None->equals($o->scope);

        $alsoWarnings = $o->gateBlocksOnWarnings() ? ' or warnings' : T_String::empty();

        $gate = match (true) {
            GitGateStage::None->equals($o->gate) => 'No git gate — nothing blocks commits or pushes.',
            GitGateStage::PreCommit->equals($o->gate) => "Pre-commit gate blocks sins{$alsoWarnings} on staged files.",
            $isPenance => "NO commit gate — commit progress freely. The pre-push gate blocks pushing while ANY sins{$alsoWarnings} remain.",
            default => "Pre-push gate blocks pushing while the branch has sins{$alsoWarnings} (no commit gate; reckon once before pushing).",
        };

        $cadence = match (true) {
            $o->perPhaseNudges => 'Cadence: judge each phase as you go — fix findings before the next phase.',
            $isPenance => 'Cadence: a CLEANUP — drive the WHOLE backlog to zero, root causes first (`pilgrimage` then `next`, reading each output in full). Commit progress freely; NEVER skip a messy file (that is the job). Push only when clean.',
            GitGateStage::PrePush->equals($o->gate) => 'Cadence: GRIND — do NOT run judge, the test suite, or ANY gate between phases, even though your default habit (and CLAUDE.md) is to verify each step. That habit is SUSPENDED here: implement the whole plan phase by phase, commit freely, and reckon (judge + run tests) ONCE before pushing. Running checks mid-grind is the mistake to avoid.',
            default => 'Cadence: no per-phase nudges.',
        };

        // Cadence (judge), test cadence, and autonomy are all DERIVED from the
        // profile's behaviour — so a profile declares its behaviour once and the
        // briefing follows. Autonomy is implementation-scoped (plan mode is free).
        return array_values(array_filter([
            "Bare `judge` scope: {$scope}.",
            $gate,
            $o->allowWarnings ? 'Warnings are flagged.' : 'Warnings are silenced (sins only).',
            $cadence,
            $o->behaviour->testBriefing(),
            $o->behaviour->autonomyBriefing(),
        ]));
    }

    /**
     * The full SessionStart briefing for a profile (used for the Short/grind body;
     * Full-briefing profiles get scripture instead).
     *
     * @return list<string>
     */
    private function briefingLines(Profile $profile): array
    {
        $runner = ClaudeHooksInstaller::runnerFor($this->basePath);
        $r = $runner[0] . $runner[1];

        $lines = [
            "CODE COMMANDMENTS — profile: {$profile->name()}",
            T_String::empty(),
        ];

        foreach ($this->contractSummary($profile) as $line) {
            $lines[] = $line;
        }

        $o = $profile->options();
        $isPenance = GitGateStage::PrePush->equals($o->gate) && JudgeScope::None->equals($o->scope);

        if ($isPenance) {
            $lines[] = T_String::empty();
            $lines[] = 'This is a CLEANUP pass. Walk the doctrines root-cause first, forward-only:';
            $lines[] = "  {$r}repent            # first, bulk-fix the [AUTO-FIXABLE] findings";
            $lines[] = "  {$r}pilgrimage        # begin the walk — ONE prophet at a time, pillar by pillar";
            $lines[] = "  {$r}next              # after fixing/absolving every shown location, advance";
            $lines[] = 'READ each pilgrimage/next output IN FULL — never head/tail/truncate it, or you WILL miss locations. `next` re-checks the current prophet and refuses to advance until it is clean (forward-only — it never revisits a passed one, so you cannot loop). Commit progress freely — nothing blocks a commit. NEVER skip a messy file; that is the job. The pre-push gate blocks pushing while sins or admonitions remain.';
        } elseif (GitGateStage::PrePush->equals($o->gate)) {
            $lines[] = T_String::empty();
            $lines[] = 'Implement the entire plan phase by phase. Do NOT run judge, the test suite, or ANY gate between phases — even though your default habit (and CLAUDE.md) is to verify each step. That habit is SUSPENDED in grind: running checks mid-grind is the mistake to avoid. Commit each phase freely and keep moving. (Autonomy during implementation is set above; in plan mode you still ask freely.)';
            $lines[] = "Only when the WHOLE plan is done: run `{$r}judge` and your full test suite ONCE. If judge flags anything, walk it with `{$r}pilgrimage` then `{$r}next` (read the FULL output — never truncate it), fixing or absolving each, then push.";
            $lines[] = 'The pre-push gate blocks the push until the branch has no unresolved sins or admonitions (each fixed or absolved with a reason).';
        } elseif (GitGateStage::PreCommit->equals($o->gate)) {
            $lines[] = T_String::empty();
            $lines[] = "When `{$r}judge` flags a phase, walk the findings before you stage the next: run `{$r}pilgrimage` then `{$r}next` — ONE prophet at a time, with its full scripture and EVERY location.";
            $lines[] = 'READ each pilgrimage/next output IN FULL — never head/tail/truncate it, or you WILL miss locations. `next` re-checks the current prophet and refuses to advance until it is clean (forward-only — it never loops back). Fix each, or absolve a GENUINE false positive with a reason. The pre-commit gate blocks the commit until your staged files are clean.';
        }

        return $lines;
    }

    private function readState(): ?string
    {
        return $this->readMarker(self::STATE);
    }

    private function writeState(string $name): void
    {
        $this->writeMarker(self::STATE, $name);
    }

    private function readBriefed(): ?string
    {
        return $this->readMarker(self::BRIEFED);
    }

    private function writeBriefed(string $name): void
    {
        $this->writeMarker(self::BRIEFED, $name);
    }

    private function readMarker(string $relative): ?string
    {
        $path = $this->basePath . '/' . $relative;

        if (! is_file($path)) {
            return null;
        }

        $value = trim((string) @file_get_contents($path));

        return $value === '' ? null : $value;
    }

    private function writeMarker(string $relative, string $value): void
    {
        $path = $this->basePath . '/' . $relative;

        @mkdir(dirname($path), 0755, true);
        @file_put_contents($path, $value . T_String::NEWLINE);
    }
}
