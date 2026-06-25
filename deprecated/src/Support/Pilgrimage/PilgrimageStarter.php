<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pilgrimage;

use JesseGall\CodeCommandments\Support\Profiles\Briefing;
use JesseGall\CodeCommandments\Support\Profiles\ProfileService;

/**
 * Orchestrates `pilgrimage [<PROPHET>]` for both command variants: resolves an
 * optional single-prophet target (strictly), enforces "WHICH PROPHET?" under the
 * repentr profile, refuses to clobber an in-flight walk, and guards a symptom target
 * whose root cause still fires. Returns the walk's first step to render, or null when
 * it has already emitted a refusal / menu (the caller renders nothing more).
 */
final class PilgrimageStarter
{
    /**
     * @param  callable(string): void  $emit
     * @return array<string, mixed>|null  the step to render, or null if refused/menu-shown
     */
    public static function start(PilgrimageRunner $runner, string $basePath, ?string $prophetArg, callable $emit): ?array
    {
        $repentr = Briefing::Repentr->equals(ProfileService::resolve($basePath)->options()->briefing);

        $prophetArg = $prophetArg !== null && trim($prophetArg) !== '' ? trim($prophetArg) : null;

        // repentr without a named prophet: refuse and present the ranked menu — the
        // "WHICH PROPHET?" question is enforced here, not left to briefing prose.
        if ($prophetArg === null && $repentr) {
            self::emitMenu($runner, $emit);

            return null;
        }

        $onlyProphet = null;

        if ($prophetArg !== null) {
            $resolved = $runner->resolveProphet($prophetArg);

            if ($resolved['class'] === null) {
                self::emitNoMatch($prophetArg, $resolved['candidates'], $emit);

                return null;
            }

            $onlyProphet = $resolved['class'];

            // Don't let a single-prophet walk repent a symptom whose root cause still
            // fires — that launders the underlying invariant violation.
            $cause = $runner->unresolvedCauseFor($onlyProphet);

            if ($cause !== null) {
                $short = self::shortName($onlyProphet);
                $emit("⚠ {$short} is a SYMPTOM of an unresolved root cause: {$cause} still fires in scope.");
                $emit("   Repenting {$short} alone could launder a real bug. Repent the cause first:");
                $emit("       commandments pilgrimage {$cause}");
                $emit("   Then return to {$short}.");

                return null;
            }
        }

        $step = $runner->begin($onlyProphet);

        if (($step['error'] ?? false) === true) {
            $current = $step['inProgress'] ?? null;
            $on = is_string($current) && $current !== '' ? ' (on ' . self::shortName($current) . ')' : '';
            $emit("⛔ A pilgrimage is already in progress{$on}. Finish it with `commandments next` until complete, or leave it with `commandments abandon`, before starting another.");

            return null;
        }

        return $step;
    }

    /**
     * @param  callable(string): void  $emit
     */
    private static function emitMenu(PilgrimageRunner $runner, callable $emit): void
    {
        $counts = $runner->prophetFindingCounts();

        $emit('❓ WHICH PROPHET should I repent? repentr walks exactly ONE. PRESENT these to the user');
        $emit('   and let THEM choose — do NOT pick one yourself:');

        if ($counts === []) {
            $emit('   (no prophet currently has findings — the scope is clean. Ask whether to switch profiles.)');

            return;
        }

        $i = 1;

        foreach ($counts as $short => $count) {
            $emit(sprintf('   %d. %s — %d finding%s', $i++, $short, $count, $count === 1 ? '' : 's'));
        }

        $emit('Once the user names one:  commandments pilgrimage <NAME>   (partial names work, like judge --prophet)');
        $emit('This is NOT an error — STOP here and wait for the user to choose. Do not start a walk on your own.');
    }

    /**
     * @param  list<string>  $candidates
     * @param  callable(string): void  $emit
     */
    private static function emitNoMatch(string $needle, array $candidates, callable $emit): void
    {
        if ($candidates === []) {
            $emit("No prophet matches '{$needle}'.");

            return;
        }

        $emit(count($candidates) === 1
            ? "No prophet matches '{$needle}'."
            : "'{$needle}' matches " . count($candidates) . ' prophets — name exactly one:');

        foreach ($candidates as $short) {
            $emit("   {$short}");
        }
    }

    private static function shortName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts) ?: $fqcn;
    }
}
