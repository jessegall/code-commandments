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
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $introducedIn,
        public readonly string $purpose,
        public readonly array $prophets = [],
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
