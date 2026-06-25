<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Profiles;

use JesseGall\CodeCommandments\Support\CompareSelf;

/**
 * Which agent briefing body a profile injects at session-start (only read when
 * {@see ProfileOptions::$briefAgent} is true).
 *
 * - {@see Briefing::Short}: the grind contract — implement the whole plan, no
 *   judge/test between phases, reckon + test before push.
 * - {@see Briefing::Full}: the complete scripture (phased / sins-only).
 * - {@see Briefing::Repentr}: the single-prophet repent contract — ask WHICH
 *   prophet, then drive only its findings to zero.
 */
enum Briefing: string
{
    use CompareSelf;

    /** The grind contract — implement the whole plan, reckon + test before push. */
    case Short = 'short';

    /** The complete scripture (phased / sins-only). */
    case Full = 'full';

    /** The single-prophet repent contract (repentr). */
    case Repentr = 'repentr';
}
