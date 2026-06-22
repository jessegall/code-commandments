<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Profiles;

/**
 * Which agent briefing body a profile injects at session-start (only read when
 * {@see ProfileOptions::$briefAgent} is true).
 *
 * - {@see Briefing::Short}: the grind contract — implement the whole plan, no
 *   judge/test between phases, reckon + test before push.
 * - {@see Briefing::Full}: the complete scripture (phased / sins-only).
 */
enum Briefing: string
{
    /** The grind contract — implement the whole plan, reckon + test before push. */
    case Short = 'short';

    /** The complete scripture (phased / sins-only). */
    case Full = 'full';
}
