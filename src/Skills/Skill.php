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
     * Does this skill's worked example need its docblocks left exactly where they are?
     *
     * Normally a fixture's docblock is lifted into `//` lines above the snippet — it is an
     * explanation for the reader, not part of the code being shown. For a skill ABOUT documentation
     * the docblock is the subject: lift it and the example no longer demonstrates anything.
     */
    public function examplesKeepDocblocks(): bool
    {
        return false;
    }

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
     * The skill's flat, loadable id — the directory it is published to in the library AND its
     * `SKILL.md` frontmatter `name`, which agents require to agree. They discover skills one level
     * deep, so the engine/slug is flattened with `-`: `backend/absence` →
     * `commandments-backend-absence`.
     */
    public function id(): string
    {
        return self::idFor($this->slug);
    }

    /**
     * The loadable id for a bare slug — the same flatten every consumer of "how a skill is named"
     * shares (the report that tells an agent which skill to load, the publisher that writes the
     * directory), so the id has ONE home and never drifts from where the skill is published.
     */
    public static function idFor(string $slug): string
    {
        return 'commandments-' . str_replace('/', '-', $slug);
    }

    /**
     * The skill rendered as a briefing bullet — by the id an agent loads it with.
     */
    public function bullet(): string
    {
        return "- **`{$this->id()}`** — {$this->summary()}";
    }

    /**
     * Tell an agent to load the skill for $slug — the ONE wording, so the console report, the
     * checklist and the scaffolder all say it the same way. It names the skill and stops: HOW a
     * skill is loaded differs per agent (a tool, a `/`-command, reading the file), and naming one
     * agent's mechanism is an instruction the others cannot follow.
     */
    public static function loadInstruction(string $slug): string
    {
        return 'LOAD the skill `' . self::idFor($slug) . '` before fixing';
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
