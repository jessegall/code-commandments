<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills;

/**
 * A dense REFERENCE document a {@see Skill} carries alongside its teaching — the mechanics a fixer needs
 * (interface signatures, attribute catalogues, config flags) that would bloat the principle. Generated to
 * `skills/commandments/<slug>/reference/<name>.md` and linked from the SKILL.md's `## Reference` section,
 * so the teaching stays lean while the details ship with it. Pure content — the class is the source of truth.
 */
final readonly class Reference
{
    public function __construct(
        /** The kebab-case file stem — becomes `reference/<name>.md` and the link target. */
        public string $name,
        /** The human title shown in the SKILL.md reference list and as the doc's H1. */
        public string $title,
        /** The markdown body (without the H1 — the renderer adds it from the title). */
        public string $body,
    ) {}
}
