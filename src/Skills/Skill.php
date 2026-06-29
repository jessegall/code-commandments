<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills;

/**
 * One teaching skill: its slug (the `skills/<slug>/` directory and the published
 * `commandments-<slug>` skill), the one-line summary shown in the consumer
 * briefing, and which tier it loads in.
 */
final class Skill
{
    public function __construct(
        public readonly string $slug,
        public readonly string $summary,
        public readonly Tier $tier,
    ) {}

    /**
     * The skill rendered as a briefing bullet.
     */
    public function bullet(): string
    {
        return "- **`{$this->slug}`** — {$this->summary}";
    }
}
