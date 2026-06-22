<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Profiles;

/**
 * What a bare `judge` (no explicit scope flag) looks at under a given profile.
 * Also the scope the profile's git gate judges against.
 *
 * - {@see JudgeScope::Staged}: only files staged for commit (the pre-commit path).
 * - {@see JudgeScope::Branch}: everything changed since the branch diverged from
 *   its base, INCLUDING already-committed work — survives intermediate commits
 *   (the grind "reckon at the end" path).
 * - {@see JudgeScope::None}: no profile-implied scope; a bare `judge` scans the
 *   full scroll.
 */
enum JudgeScope: string
{
    /** No profile-implied scope — a bare `judge` scans the full scroll. */
    case None = 'none';

    /** Only files staged for commit (the pre-commit gate path). */
    case Staged = 'staged';

    /** Everything changed since the branch base, including committed work (the grind reckoning). */
    case Branch = 'branch';
}
