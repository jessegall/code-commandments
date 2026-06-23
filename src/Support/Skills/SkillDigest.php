<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Skills;

use JesseGall\PhpTypes\T_String;

/**
 * A compact, always-loadable index of the shipped skills — printed by the
 * `skills` command and injected at session start so an agent KNOWS the coding-
 * rule playbooks exist and when to reach for each, instead of relying on
 * passive skill discovery (which is unreliable for coding rules). Each line is
 * the slug + a one-line trigger; the full `SKILL.md` and its `reference/*.md`
 * are pulled on demand, so this stays cheap.
 */
final class SkillDigest
{
    public static function render(): string
    {
        $lines = [
            'CODE COMMANDMENTS SKILLS — the project\'s coding-rule playbooks live in .claude/skills/commandments-<slug>/.',
            'Before you WRITE or REVIEW code in one of these subjects, READ that skill\'s SKILL.md first (it links deeper reference/ files). Do not guess the rules.',
            T_String::empty(),
        ];

        foreach (SkillRegistry::all() as $skill) {
            if (! $skill->autoload) {
                // A command-triggered skill (e.g. handoff) — installed + natively
                // discoverable, but not force-injected every session.
                continue;
            }

            $lines[] = sprintf('- %s — %s', $skill->slug, self::trigger($skill->purpose));
        }

        return implode(T_String::NEWLINE, $lines);
    }

    /**
     * The first clause of a skill's purpose, capped — enough to know WHEN it
     * applies without dumping the whole paragraph into every session.
     */
    private static function trigger(string $purpose): string
    {
        $parts = preg_split('/(?<=[.;])\s+/', trim($purpose), 2);
        $first = $parts[0] ?? $purpose;

        return mb_strlen($first) > 200 ? mb_substr($first, 0, 199) . '…' : $first;
    }
}
