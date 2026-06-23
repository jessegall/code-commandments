<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Profiles;

use JesseGall\CodeCommandments\Support\CompareSelf;

/**
 * WHEN an activity (judging, testing) happens under a profile — the cadence the
 * hook generation and the agent guidance are derived from. Orthogonal to
 * {@see JudgeScope} (what is looked at): cadence is the timing, scope is the
 * breadth.
 *
 * - {@see Phase::Never}: the activity never happens (disabled).
 * - {@see Phase::EachPhase}: after every phase/commit — the per-commit nudge +
 *   a keep-going gate over the current changes (phased / sins-only).
 * - {@see Phase::AtEnd}: deferred to ONE reckoning at the end, enforced by the
 *   pre-push gate; NOT between phases (grind). The keep-going hook never runs it.
 * - {@see Phase::UntilClean}: run every Stop until nothing remains, over the
 *   whole codebase — the cleanup loop (penance).
 */
enum Phase: string
{
    use CompareSelf;

    case Never = 'never';

    case EachPhase = 'each-phase';

    case AtEnd = 'at-end';

    case UntilClean = 'until-clean';
}
