<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills;

use JesseGall\CodeCommandments\Languages;

/**
 * The canon published as a LOADABLE skill: one document naming every discipline, when each fires, and
 * the commands that find and fix them. It is the {@see Briefing} verbatim — the same words a project's
 * `AGENTS.md` carries — so an agent that reaches for it after a compaction, or to answer "which skill
 * covers this?", gets the canon rather than a second summary of it that could disagree.
 */
final class Router
{
    /**
     * The id an agent loads it by. Unprefixed, because it is not one discipline among the
     * `commandments-*` set — it is the way in to all of them.
     */
    public const string ID = 'commandments';

    /**
     * WHEN to reach for it. Every other skill's trigger names a syntax you are about to write; this
     * one's names the moments you need the MAP instead of one discipline.
     */
    private const string TRIGGER = 'The index of this project\'s architectural disciplines — every '
        . 'code-commandments skill, when each one fires, and the commands that find and fix sins '
        . '(`judge`, `info`, `repent`, `report`, `make`). Load this when you start work in this '
        . 'codebase, when you need to know WHICH discipline covers a subject you are about to write, '
        . 'when a `judge` finding names a rule you do not recognise, or when a context compaction may '
        . 'have dropped the disciplines you loaded earlier.';

    /**
     * @param  string|null  $project  the consumer root, so its OWN skills are listed too
     */
    public static function render(?string $project = null, ?Languages $languages = null): string
    {
        $description = json_encode(self::TRIGGER, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return "---\nname: " . self::ID . "\ndescription: {$description}\n---\n\n"
            . "# Code Commandments — the disciplines in force here\n\n"
            . Briefing::render($project, $languages) . "\n";
    }
}
