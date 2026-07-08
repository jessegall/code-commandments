<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills;

/**
 * One teaching skill: source of truth for its generated `SKILL.md`. Hand-written concept + descriptor
 * only; enumerable sections (rules, examples, checklist) project from this skill's Sins via Catalog discovery.
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
     * WHEN to load this skill — the load condition, phrased as a trigger ("Read this BEFORE you
     * …"). It becomes the SKILL.md frontmatter `description:` the Skill loader matches an agent's
     * task against, so it names the situations that should pull the skill in, not what it teaches.
     */
    abstract public function trigger(): string;

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
     * The dense REFERENCE documents this skill ships alongside its teaching — the mechanics (interface
     * signatures, attribute catalogues, config flags) a fixer needs but that would bloat the principle.
     * Each is generated to `reference/<name>.md` next to the SKILL.md and linked from its `## Reference`
     * section. Empty by default — most skills carry all they need in the principle.
     *
     * @return list<Reference>
     */
    public function references(): array
    {
        return [];
    }

    /**
     * The skill's flat, loadable id — the `.claude/skills/<id>/` directory it's published
     * to AND its `SKILL.md` frontmatter `name`, so the Skill tool can find it. Claude Code
     * discovers skills one level deep, so the engine/slug is flattened with `-`:
     * `backend/absence` → `commandments-backend-absence`.
     */
    public function id(): string
    {
        return 'commandments-' . str_replace('/', '-', $this->slug);
    }

    /**
     * The skill rendered as a briefing bullet — by the id the Skill tool loads.
     */
    public function bullet(): string
    {
        return "- **`{$this->id()}`** — {$this->summary()}";
    }

    /**
     * Does this skill answer to the query the user typed (the `enable`/`disable`/`--skill=`
     * key)? Lenient, like {@see \JesseGall\CodeCommandments\Sins\Sin::matches}: both sides
     * reduce to lowercase alphanumerics, so `value-objects`, `ValueObjects` and the full
     * `backend/value-objects` slug all select the same skill.
     */
    public function matches(string $query): bool
    {
        $normalise = static fn (string $value): string => strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '', $value));

        return str_contains($normalise($this->slug), $normalise($query));
    }
}
