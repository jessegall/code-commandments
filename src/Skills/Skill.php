<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills;

/**
 * One teaching skill — the source of truth its `SKILL.md` is GENERATED from, with a
 * FIXED section layout shared by every skill. The hand-written part is reduced to
 * conceptual prose + the entry descriptor; everything ENUMERABLE (the rules, the
 * bad→good examples, the "when it fires" rows, the checklist) is projected from this
 * skill's {@see \JesseGall\CodeCommandments\Sins\Sin}s, so the docs can never hardcode a
 * count or drift from the detectors.
 *
 * Each skill is its OWN class under `Skills/{Backend,Frontend}/`, the way each sin is its
 * own class under `Sins/` and each detector under `Detectors/` — discovered by
 * {@see Catalog}. A consumer's own `Skills/` class auto-enrols.
 */
abstract class Skill
{
    public function __construct(
        public readonly string $slug,
        public readonly Tier $tier,
        public readonly int $order,
    ) {}

    /**
     * The `# {title}` H1.
     */
    abstract public function title(): string;

    /**
     * The frontmatter `description:` — the trigger blurb: when to load this skill.
     */
    abstract public function description(): string;

    /**
     * The one-line `>` blockquote under the title — the punchy summary of the rule.
     */
    abstract public function intro(): string;

    /**
     * The terse one-liner shown in the consumer briefing (`ClaudeSection`), not in the
     * `SKILL.md` itself.
     */
    abstract public function summary(): string;

    /**
     * The `## The principle` prose — the conceptual "why" ONLY. No rule list, no code
     * examples, no hardcoded counts (all generated from the sins).
     */
    abstract public function principle(): string;

    /**
     * The related skills, each `class-string<Skill>` => one-line note. Rendered into the
     * footer with the link GENERATED from the target skill's current slug — reference a
     * skill by CLASS, never a slug string, so a rename never leaves a stale link.
     *
     * @return array<class-string<Skill>, string>
     */
    public function related(): array
    {
        return [];
    }

    /**
     * The skill rendered as a briefing bullet.
     */
    public function bullet(): string
    {
        return "- **`{$this->slug}`** — {$this->summary()}";
    }
}
