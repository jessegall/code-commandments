<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use JesseGall\CodeCommandments\Contracts\SinRepenter;
use JesseGall\PhpTypes\T_String;

/**
 * The shared rendering behind `scripture` — one implementation the artisan and
 * standalone commands both call (they only differ in the runner shown in the
 * footer and the output sink). The commands are thin adapters.
 */
final class ScriptureService
{
    public const SUCCESS = 0;
    public const FAILURE = 1;

    /**
     * Render the scripture (whole catalogue, one scroll, or one prophet's detail)
     * via $emit. Returns a shell-style status (0 ok, 1 prophet-not-found).
     *
     * @param  array{0: string, 1: string}  $runner  e.g. ClaudeHooksInstaller::ARTISAN
     * @param  callable(string): void  $emit
     */
    public static function render(ProphetRegistry $registry, ?string $scroll, ?string $prophet, bool $detailed, array $runner, callable $emit): int
    {
        if (is_string($prophet) && $prophet !== '') {
            return self::renderProphet($registry, $prophet, $runner, $emit);
        }

        $detailed = $detailed || ($prophet !== null && $prophet !== '');
        $scrolls = (is_string($scroll) && $scroll !== '') ? [$scroll] : $registry->getScrolls();

        $emit('CODE COMMANDMENTS');
        $emit(T_String::empty());
        $emit('IMPORTANT: Never commit code with sins. Fix all violations first.');
        $emit(T_String::empty());

        foreach ($scrolls as $name) {
            if (! $registry->hasScroll($name)) {
                continue;
            }

            $emit(strtoupper($name) . ':');

            foreach ($registry->getProphets($name) as $p) {
                $short = str_replace('Prophet', T_String::empty(), class_basename($p));
                $badge = $p instanceof SinRepenter ? ' [AUTO-FIXABLE]' : T_String::empty();
                $emit("- {$short}{$badge}: {$p->description()}");

                if ($detailed) {
                    foreach (explode(T_String::NEWLINE, $p->detailedDescription()) as $line) {
                        $emit("  {$line}");
                    }
                    $emit(T_String::empty());
                }
            }

            $emit(T_String::empty());
        }

        $r = $runner[0] . $runner[1];
        $emit("Audit everything: {$r}judge");
        $emit("Walk + fix findings: {$r}pilgrimage  then  {$r}next  (read each output IN FULL — never truncate)");
        $emit("Auto-fix [AUTO-FIXABLE] sins: {$r}repent  (do NOT hand-fix these)");
        $emit("Report a false positive OR prophet bug (proactively!): {$r}report --at=path:line --reason=\"what is wrong\"");

        return self::SUCCESS;
    }

    /**
     * @param  array{0: string, 1: string}  $runner
     * @param  callable(string): void  $emit
     */
    private static function renderProphet(ProphetRegistry $registry, string $prophet, array $runner, callable $emit): int
    {
        $found = $registry->findProphet($prophet);

        if (! $found) {
            $emit("Prophet '{$prophet}' not found.");

            return self::FAILURE;
        }

        $p = $found['prophet'];
        $short = str_replace('Prophet', T_String::empty(), class_basename($p));

        $emit(strtoupper($short));
        $emit(T_String::empty());
        $emit('REQUIREMENT: ' . $p->description());

        if ($p instanceof SinRepenter) {
            $emit('[AUTO-FIXABLE with: ' . $runner[0] . $runner[1] . 'repent]');
        }

        $emit(T_String::empty());
        $emit('You MUST follow this rule exactly as described below:');
        $emit(T_String::empty());
        $emit($p->detailedDescription());

        return self::SUCCESS;
    }
}
