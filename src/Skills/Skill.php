<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills;

/**
 * One teaching skill — the source of truth its `SKILL.md` is GENERATED from. It owns
 * the entry descriptor ({@see $title}, frontmatter {@see $description}, the `>`
 * {@see $tagline}), the teaching {@see body} (principle + forms, authored once as a
 * nowdoc), and the {@see related} footer. The "Bad → good" and "When it fires"
 * sections are NOT stored here — they are projected from the skill's
 * {@see \JesseGall\CodeCommandments\Sins\Sin}s (each sin owns its bad→good example and
 * one-line description), so the docs can't drift from the detectors.
 *
 * Each skill is its OWN class under `Skills/{Backend,Frontend}/`, the way each sin is
 * its own class under `Sins/` and each detector under `Detectors/` — registered by
 * existing, discovered by {@see Catalog}. A consumer's own `Skills/` class auto-enrols.
 */
abstract class Skill
{
    public function __construct(
        public readonly string $slug,
        public readonly string $title,
        public readonly string $description,
        public readonly string $tagline,
        public readonly string $summary,
        public readonly Tier $tier,
        public readonly int $order,
    ) {}

    /**
     * The teaching prose — the principle and forms — between the tagline and the
     * generated "Bad → good" section. Authored once as a nowdoc per skill.
     */
    abstract public function body(): string;

    /**
     * The related skills, each a `class-string<Skill>` => one-line note. Rendered into
     * the "Relationship to the other skills" footer with the link GENERATED from the
     * target skill's current slug — reference a skill by CLASS, never a slug string, so
     * a renamed skill can never leave a stale link.
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
        return "- **`{$this->slug}`** — {$this->summary}";
    }
}
