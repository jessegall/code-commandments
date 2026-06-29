<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills;

/**
 * When a skill must be loaded — the two tiers of the consumer briefing.
 */
enum Tier
{
    /** Load at the start of every coding session, before exploring or editing. */
    case Mandatory;

    /** Load the moment the work touches the subject. */
    case KeepInMind;
}
