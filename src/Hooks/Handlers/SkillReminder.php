<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks\Handlers;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Config;
use JesseGall\CodeCommandments\Detector;
use JesseGall\CodeCommandments\Detectors\Catalog;
use JesseGall\CodeCommandments\Frontend\Detector as FrontendDetector;
use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Hooks\HookBinding;
use JesseGall\CodeCommandments\Hooks\HookEvent;
use JesseGall\CodeCommandments\Languages;
use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Vue\Codebase as VueCodebase;
use Throwable;

/**
 * Names the skill for the code you JUST wrote. A description can only say when a discipline is
 * probably relevant; the detectors can say that it definitely is — so this reads the file the edit
 * landed in and, when a rule fires there, names the skill that teaches the fix while the code is
 * still in mind. Not a `judge` run: only the rules that can judge one file
 * ({@see Catalog::singleFile}), reported as a nudge, never blocking.
 */
final class SkillReminder extends Hook
{
    /**
     * The tools whose edits this watches — file writes.
     */
    private const array WRITERS = ['Edit', 'Write', 'MultiEdit'];

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
        return 'After an edit, checks the file against the rules that can judge one file and names the skill that teaches the fix.';
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
        $file = $this->judged($event->root, $event->filePath(), $config);

        if ($file === null || filesize($file) > self::BUDGET) {
            return $this->pass();
        }

        $sins = $this->sinsIn($file, Languages::from($config));

        return $sins === [] ? $this->pass() : $this->inject($event, $this->nudge($file, $sins));
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
     * Every single-file rule that fires in $file, as "sin name at line" keyed by the skill that
     * teaches the fix. A rule that throws on one file in isolation is a rule that could not answer,
     * which is silence — this is a nudge, and a nudge is never worth a broken tool call.
     *
     * @return array<string, list<string>>  skill slug => the sins found
     */
    private function sinsIn(string $file, Languages $languages): array
    {
        $backend = str_ends_with($file, '.php') ? Codebase::scan($file) : null;
        $frontend = $backend === null ? VueCodebase::scan($file, languages: $languages) : null;
        $found = [];

        foreach (Catalog::singleFile() as $detector) {
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
     * @param  array<string, list<string>>  $sins
     */
    private function nudge(string $file, array $sins): string
    {
        $lines = ['Code Commandments — the edit you just made to `' . basename($file) . '` breaks a rule. '
            . 'Fix it now, at its SOURCE, while the code is still in front of you:'];

        foreach ($sins as $slug => $found) {
            $shown = array_slice(array_unique($found), 0, self::SHOWN);
            $rest = count(array_unique($found)) - count($shown);

            $lines[] = '  • ' . implode('; ', $shown) . ($rest > 0 ? " (+{$rest} more)" : '')
                . "\n    " . Skill::loadInstruction($slug) . ' — load it even if you believe you already have.';
        }

        $lines[] = 'Run `vendor/bin/commandments info <sin>` if a rule is not one you recognise. '
            . 'This check reads ONE file, so it is not the whole picture — `judge` still is.';

        return implode("\n", $lines);
    }
}
