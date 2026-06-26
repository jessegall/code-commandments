<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills;

/**
 * Renders the auto-managed "Skills — load before you work" block injected into a
 * consumer's CLAUDE.md. The prose is fixed; the two skill lists are generated from
 * {@see Skills}, so adding or re-tiering a skill updates the briefing.
 */
final class ClaudeSection
{
    public const string BEGIN = '<!-- BEGIN: code-commandments skills (auto-managed — do not edit between these markers) -->';

    public const string END = '<!-- END: code-commandments skills -->';

    public static function render(): string
    {
        $mandatory = self::bullets(Tier::Mandatory);
        $keepInMind = self::bullets(Tier::KeepInMind);

        $body = <<<MD
        ## Skills — load before you work

        Code style in this package lives in the code-commandments skills under
        `.claude/skills/commandments-*`. Two tiers. They are the source of truth for
        every architecture/style decision — read the relevant one (via the Skill tool)
        before writing or reviewing code.

        **Leave it cleaner than you found it — the gentleman's duty.** When you touch a
        file (or even read past a sin while working in it), fix it; do not excuse it as
        "pre-existing baseline." Every finding on code you come across is yours to resolve
        — or, if it is a genuine false positive, say so and why. Resolve, don't rationalise.

        **Prefer cleaning up the code over laundering the problem.** When several call
        sites need fixing, fix them. Do NOT add a wrapper, constructor override, cast, or
        suppression that hides the smell internally just to avoid touching them. A shortcut
        that makes the code *pass* while growing it is the wrong move; the real fix touches
        the call sites — even when one is awkward to reach. This is `fix-at-the-source`
        applied as a work ethic.

        **MANDATORY LOAD — load these at the start of every coding session, before you
        explore-to-plan or edit a single line** (via the Skill tool):

        {$mandatory}

        Do not start work without all of them loaded.

        **KEEP IN MIND — load the moment the work touches them:**

        {$keepInMind}

        Find sins with `vendor/bin/commandments judge src` (add `--skill=NAME` to scope to
        one group); fix each at the source per its skill, then re-run until clean.
        MD;

        return self::BEGIN . "\n" . $body . "\n" . self::END;
    }

    private static function bullets(Tier $tier): string
    {
        return implode("\n", array_map(static fn (Skill $skill): string => $skill->bullet(), Skills::inTier($tier)));
    }
}
