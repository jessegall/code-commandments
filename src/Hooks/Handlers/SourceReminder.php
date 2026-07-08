<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks\Handlers;

use JesseGall\CodeCommandments\Config;

use JesseGall\CodeCommandments\Hooks\Counter;
use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Hooks\HookBinding;
use JesseGall\CodeCommandments\Hooks\HookEvent;
/**
 * A `PreToolUse` nudge for the symptom-vs-source trap at the ONE place `judge` is blind: an edit to a
 * test / stub / fixture / mock. Those files are (correctly) OUTSIDE the judged source roots, so a
 * detector never fires on them — which means when an agent is standing in a failing test and reaches for
 * the nearest surface, nothing links it back to the cardinal rule. This closes that gap: when the agent
 * edits an unjudged symptom surface, it injects a reminder that a failing test usually means the
 * PRODUCTION code needs the fix — trace to the source, don't launder the failure in the test.
 *
 * Non-blocking (it never stops a legitimate test edit) and rate-limited via a {@see Counter} that a fresh
 * session re-arms, so it lands once when the agent first touches a test each session, then only sparingly.
 */
final class SourceReminder extends Hook
{
    /**
     * A tighter cadence than the broad reminders (25): this fires only on the narrow signal of a
     * test/stub edit, and catching a laundered fix early matters — so it lands on the FIRST such edit of
     * a session and then once every 10.
     */
    private const int INTERVAL = 10;

    /** The tools whose edits this watches — file writes; a Bash edit isn't seen and isn't the trap. */
    private const array WRITERS = ['Edit', 'Write', 'MultiEdit'];

    /** Path SEGMENTS that mark a test/fixture tree — matched exactly, so `src/Contest/` never counts. */
    private const array SURFACE_DIRS = [
        'tests', 'test', 'spec', 'specs', 'stubs', 'fixtures', 'fixture', 'mocks', '__mocks__', '__snapshots__', 'snapshots',
    ];

    public function summary(): string
    {
        return "When you edit a test/stub/fixture (which `judge` never scans), nudges you to check the real fix belongs at the SOURCE.";
    }

    public function bindings(): array
    {
        return array_map(static fn (string $tool): HookBinding => new HookBinding('PreToolUse', $tool), self::WRITERS);
    }

    protected function onPreToolUse(HookEvent $event): int
    {
        if (! in_array($event->tool(), self::WRITERS, true)) {
            return $this->pass(); // The shared PreToolUse moment fires for every tool — self-filter to writes.
        }

        $file = $event->filePath();

        if ($file === '' || ! self::isSymptomSurface($file) || $this->isJudged($event->root, $file)) {
            return $this->pass(); // Not a test/stub, or a project that DOES judge it — no blind spot to cover.
        }

        // Count symptom-surface edits (not all tool uses): fire on the first of the session, then every 10.
        $counter = Counter::named($event->root, 'source-remind', 'nudges "fix at the source, not the test" on an edit to an unjudged test/stub', every: self::INTERVAL);

        return $counter->firstThenEvery() ? $this->inject($event, $this->nudge($file)) : $this->pass();
    }

    private function nudge(string $file): string
    {
        return "Code Commandments — you're editing `" . basename($file) . "`, a test/stub/fixture that `judge` never "
            . "scans, so nothing here will flag a symptom-fix. If you're changing it to make a failing check pass, first "
            . "ask WHERE the failure is born: a failing test usually means the PRODUCTION code is missing behaviour — a "
            . "method, a type, a total value on the real type. Fix it THERE (trace to the source; re-open the relevant "
            . "skill), and change this file only if the TEST itself is wrong. If this is just legitimate test coverage, carry on.";
    }

    /**
     * Is $file a test / stub / fixture / mock — a "symptom surface" where a fix laundered to make a check
     * pass hides the real defect? Genuine path scanning (a directory name, a filename suffix), not code
     * structure, so no engine is owed: a segment names a test tree, or the basename carries a test/stub/
     * fixture/snapshot suffix.
     */
    private static function isSymptomSurface(string $file): bool
    {
        $lower = strtolower(str_replace('\\', '/', $file));
        $segments = explode('/', $lower);

        foreach ($segments as $segment) {
            if (in_array($segment, self::SURFACE_DIRS, true)) {
                return true;
            }
        }

        $base = end($segments) ?: '';

        foreach (['test.php', 'stub.php', 'fixture.php', 'mock.php'] as $suffix) {
            if (str_ends_with($base, $suffix)) {
                return true;
            }
        }

        foreach (['.test.', '.spec.'] as $fragment) {
            if (str_contains($base, $fragment)) {
                return true;
            }
        }

        return str_ends_with($base, '.stub') || str_ends_with($base, '.snap') || str_ends_with($base, '.fixture');
    }

    /**
     * Does `judge` actually cover $file — i.e. does it sit under a declared source root? When it does (a
     * project that scans its tests), there's no blind spot and the nudge stays silent. With no roots
     * declared yet, nothing is provably judged, so the symptom surface alone decides.
     */
    private function isJudged(string $root, string $file): bool
    {
        $absolute = str_starts_with($file, '/') ? $file : rtrim($root, '/') . '/' . ltrim($file, '/');

        foreach (Config::load($root)->sourceRoots() as $relative) {
            $rootDir = $relative === '.' ? rtrim($root, '/') : rtrim($root, '/') . '/' . trim($relative, '/');

            if ($absolute === $rootDir || str_starts_with($absolute, $rootDir . '/')) {
                return true;
            }
        }

        return false;
    }
}
