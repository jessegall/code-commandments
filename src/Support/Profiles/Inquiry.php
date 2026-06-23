<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Profiles;

use JesseGall\CodeCommandments\Support\CompareSelf;

/**
 * HOW READILY the agent should interrupt the user with a question instead of
 * proceeding on its own — the profile's autonomy bar, surfaced as guidance in
 * the briefing and the keep-going hook (it shapes wording, it does not mechanically
 * stop the agent). A ladder from most autonomous to most collaborative.
 *
 * - {@see Inquiry::Never}: never pause to ask; work around obstacles and keep
 *   going to the end. The most autonomous — for a fully-specified, unattended run.
 * - {@see Inquiry::WhenBlocked}: ask ONLY when genuinely stuck — a decision only
 *   the user can make, information that cannot be found or inferred, or an
 *   unrecoverable failure. Otherwise proceed (grind / penance: the plan is the
 *   agreement, execute it).
 * - {@see Inquiry::OnDecisions}: also surface consequential or ambiguous choices
 *   BEFORE acting — an irreversible action, a real trade-off, or several valid
 *   approaches (phased / sins-only: confirm meaningful forks as you go).
 * - {@see Inquiry::Freely}: ask whenever a question would clarify or de-risk the
 *   work — collaborative pairing, the least autonomous.
 */
enum Inquiry: string
{
    use CompareSelf;

    case Never = 'never';

    case WhenBlocked = 'when-blocked';

    case OnDecisions = 'on-decisions';

    case Freely = 'freely';
}
