<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks\Handlers;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Config;
use JesseGall\CodeCommandments\Detector;
use JesseGall\CodeCommandments\Detectors\Catalog;
use JesseGall\CodeCommandments\Detectors\CrossFileSet;
use JesseGall\CodeCommandments\Frontend\Detector as FrontendDetector;
use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Hooks\HookBinding;
use JesseGall\CodeCommandments\Hooks\HookEvent;
use JesseGall\CodeCommandments\Hooks\TouchedSources;
use JesseGall\CodeCommandments\Languages;
use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Vue\Codebase as VueCodebase;
use Throwable;

/**
 * Names the skill for the code you JUST wrote. A description can only say when a discipline is
 * probably relevant; the detectors can say that it definitely is — so this reads the file the edit
 * landed in and, when a rule fires there, names the skill that teaches the fix while the code is
 * still in mind. It watches the shell as well as the write tools, because an edit made with a
 * heredoc is an edit ({@see TouchedSources}). Not a `judge` run: only the rules that can judge one
 * file ({@see Catalog::singleFile}), reported as a nudge, never blocking.
 */
final class SkillReminder extends Hook
{
    /**
     * The tools whose edits this watches. A write NAMES the file it wrote; `Bash` does not — a
     * heredoc, a `sed -i` or a script is an edit all the same, and on a session that prefers the
     * shell this was every edit there was ({@see TouchedSources} finds them).
     */
    private const array WRITERS = ['Edit', 'Write', 'MultiEdit', 'Bash'];

    /**
     * How many changed files one shell command is checked over. A command can touch a whole tree (a
     * checkout, an install); this is a nudge about what was just written, so it reads the few most
     * recent and leaves the rest to `judge`.
     */
    private const int TOUCHED = 5;

    /**
     * How many sins it names before it stops listing. The nudge is a pointer to a skill, not a
     * report; `judge` is where the full list belongs.
     */
    private const int SHOWN = 3;

    /**
     * The largest file worth checking on the spot, in bytes. Every rule runs over the whole file, so
     * the cost tracks its size — an ordinary source file is milliseconds, a several-thousand-line one
     * is seconds, and a nudge that makes every edit wait is worse than no nudge. Past the budget the
     * file is left to `judge`, which is the thorough check either way.
     */
    private const int BUDGET = 120_000;

    public function summary(): string
    {
        return 'After an edit — including one made with the shell — checks the files against the rules that can judge one file and names the skill that teaches the fix.';
    }

    public function bindings(): array
    {
        return array_map(static fn (string $tool) => new HookBinding('PostToolUse', $tool), self::WRITERS);
    }

    protected function onPostToolUse(HookEvent $event): int
    {
        if (! in_array($event->tool(), self::WRITERS, true)) {
            return $this->pass(); // The shared PostToolUse moment fires for every tool — self-filter to writes.
        }

        $config = Config::load($event->root);
        $files = $this->edited($event, $config);

        // The rules this project RUNS: its own registered beside the shipped ones, minus what it
        // disabled. A rule it silenced must not nudge, and a rule it wrote itself must.
        $configured = $config->apply(Catalog::backend(), Catalog::frontend());
        $rules = [...$configured['backend'], ...$configured['frontend']];

        $single = Catalog::singleFile(CrossFileSet::forProject($event->workspace(), $rules), $rules);
        $sins = [];

        foreach ($files as $file) {
            $sins = array_merge_recursive($sins, $this->sinsIn($file, Languages::from($config), $single));
        }

        return $sins === [] ? $this->pass() : $this->inject($event, $this->nudge($files, $sins));
    }

    /**
     * The judged files this tool call wrote, small enough to check on the spot. A write says which
     * one; a shell command does not, so its edits are the judged files that CHANGED — and a tool
     * that named its own file still moves the mark, or the next shell command would claim it again.
     *
     * @return list<string>
     */
    private function edited(HookEvent $event, Config $config): array
    {
        $touched = new TouchedSources($event->workspace(), $event->root, $config);

        if ($event->tool() === 'Bash') {
            $files = $touched->claim(self::TOUCHED);
        } else {
            $files = array_filter([$this->judged($event->root, $event->filePath(), $config)]);
            $touched->markSeen();
        }

        return $this->withinBudget($files);
    }

    /**
     * The files a single call will actually read — as many as fit in ONE file's budget, since the
     * cost of judging tracks the bytes. A shell command that rewrote a tree therefore costs no more
     * than a single `Write` ever did, and what it drops is the older half of the burst.
     *
     * @param  list<string>  $files
     * @return list<string>
     */
    private function withinBudget(array $files): array
    {
        $budget = self::BUDGET;
        $affordable = [];

        foreach ($files as $file) {
            $size = filesize($file);

            if ($size === false || $size > $budget) {
                continue;
            }

            $budget -= $size;
            $affordable[] = $file;
        }

        return $affordable;
    }

    /**
     * The file this edit landed in, when it is one `judge` would scan — absolute, and null for
     * anything outside the project's declared source roots (a test, a config, a document). A rule
     * only speaks about the code it judges.
     */
    private function judged(string $root, string $file, Config $config): ?string
    {
        if ($file === '' || ! $config->isJudged($root, $file)) {
            return null;
        }

        $absolute = str_starts_with($file, '/') ? $file : rtrim($root, '/') . '/' . ltrim($file, '/');

        return is_file($absolute) ? $absolute : null;
    }

    /**
     * Every one of $rules that fires in $file, as "sin name at line" keyed by the skill that teaches
     * the fix. A rule that throws on one file in isolation is a rule that could not answer, which is
     * silence — this is a nudge, and a nudge is never worth a broken tool call.
     *
     * @param  list<Detector>  $rules  the single-file rules this project runs
     * @return array<string, list<string>>  skill slug => the sins found
     */
    private function sinsIn(string $file, Languages $languages, array $rules): array
    {
        $backend = str_ends_with($file, '.php') ? Codebase::scan($file) : null;
        $frontend = $backend === null ? VueCodebase::scan($file, languages: $languages) : null;
        $found = [];

        foreach ($rules as $detector) {
            $codebase = $this->codebaseFor($detector, $backend, $frontend);

            if ($codebase === null) {
                continue;
            }

            try {
                $matches = $detector->find($codebase);
            } catch (Throwable) {
                continue;
            }

            foreach ($matches as $match) {
                $found[$detector->sin()->slug()][] = $detector->sin()->name() . ' at ' . $match->location();
            }
        }

        return $found;
    }

    /**
     * The codebase this detector reads, or null when the edited file is not its engine's language.
     */
    private function codebaseFor(Detector $detector, ?Codebase $backend, ?VueCodebase $frontend): Codebase|VueCodebase|null
    {
        return $detector instanceof FrontendDetector ? $frontend : $backend;
    }

    /**
     * @param  list<string>  $files
     * @param  array<string, list<string>>  $sins
     */
    private function nudge(array $files, array $sins): string
    {
        $edit = count($files) === 1 ? 'the edit you just made to `' . basename($files[0]) . '`' : 'what you just wrote';

        $lines = ['Code Commandments — ' . $edit . ' breaks a rule. '
            . 'Fix it now, at its SOURCE, while the code is still in front of you:'];

        foreach ($sins as $slug => $found) {
            $shown = array_slice(array_unique($found), 0, self::SHOWN);
            $rest = count(array_unique($found)) - count($shown);

            $lines[] = '  • ' . implode('; ', $shown) . ($rest > 0 ? " (+{$rest} more)" : '')
                . "\n    " . Skill::loadInstruction($slug) . ' — load it even if you believe you already have.';
        }

        $lines[] = 'Run `vendor/bin/commandments info <sin>` if a rule is not one you recognise. '
            . 'This check reads a file at a time, so it is not the whole picture — `judge` still is.';

        return implode("\n", $lines);
    }
}
