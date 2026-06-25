<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Skills;

/**
 * A Claude Code skill the package can stamp into the consumer's
 * `.claude/skills/commandments-<slug>/` tree — the on-demand "how to do it
 * right" playbook for one architectural subject. The literal twin of
 * {@see \JesseGall\CodeCommandments\Support\Scaffolding\Scaffold}: each Skill
 * names a packaged stub directory (a `SKILL.md` plus a `reference/` folder)
 * and the prophet family it teaches.
 */
final class Skill
{
    /**
     * @param  string  $slug  the subject slug — both the stub dir name and the installed dir name
     * @param  string  $introducedIn  package version that first shipped this skill
     * @param  string  $purpose  one-line summary of what the subject teaches
     * @param  list<string>  $prophets  short names of the prophet classes this skill backs (the inverse of which surfaces as the prophet's deep-dive pointer)
     * @param  bool  $workflow  a command/workflow skill (e.g. reporting) that teaches a CLI flow rather than a prophet family — exempt from the "must back ≥1 prophet" rule
     * @param  bool  $autoload  whether to surface this skill in the session-start digest ({@see SkillDigest}). Default true; set false for a skill tied to a self-evident command an agent will trigger on its own (e.g. handoff) — still installed + natively discoverable, just not force-injected every session.
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $introducedIn,
        public readonly string $purpose,
        public readonly array $prophets = [],
        public readonly bool $workflow = false,
        public readonly bool $autoload = true,
    ) {}

    /**
     * The packaged stub directory for this subject, relative to stubs/skills/.
     * The slug doubles as the directory name (the direct twin of a scaffold's
     * stubFile).
     */
    public function stubDir(): string
    {
        return $this->slug;
    }

    /**
     * The frontmatter `name:` for the installed SKILL.md — namespaced under the
     * package so it can't collide with the consumer's own skills.
     */
    public function skillName(): string
    {
        return 'commandments-' . $this->slug;
    }
}
