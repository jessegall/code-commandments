# Example 04 — a not-found exception swallowed into `null`

**Domain:** rendering the display name for the currently authenticated user.

## The smell (`initial/`)

`UserProfileService::displayName()` is handed the **authenticated** user id, then wraps the repository
call in `try { … } catch (UserNotFoundException) { $user = null; }` and falls back to `'Guest'`. But the
id came from the session — if that user does not exist, the session is corrupt and *something is badly
wrong*. Swallowing the not-found into `null` turns that broken invariant into a silent "Guest", and every
caller compensates.

## What must be flagged, and in what order

In `UserProfileService::displayName()`:

1. **ROOT CAUSE → `NoSwallowedNotFoundProphet`** *(new — proposed in `REQUEST.md`)*: a `try/catch` whose
   catch type is a "not found" exception and whose body only assigns a sentinel (`$user = null`) is an
   invariant violation laundered into absence.
2. **SYMPTOM → `PreferOptionOverNullProphet`** + the `?-> … ?? 'Guest'` compensation: model/return the
   absence rather than `null`. Applied alone, an agent would turn the swallow into an `Option` and keep
   the `'Guest'` fallback — preserving the bug.

**Required order:** `NoSwallowedNotFoundProphet` leads. The symptom is deferred (full run) or annotated
(filtered run) so the fix is "stop swallowing," not "wrap the swallow in Option."

## The fix (`final/`)

**Remove the `try/catch`** and let `getById()` throw. The id is an invariant (it is the logged-in user);
a miss is a real, loud error — not a "Guest". The `?-> … ?? 'Guest'` compensation goes too.

**Class change: none** — the fix *removes* the defensive `try/catch` and the caller fallback. (If a
screen genuinely needs to tolerate a missing user — e.g. an admin viewing an arbitrary, user-supplied id —
that is a *different* method whose absence is genuine and should return `Option<User>`; see example 05.)

## Detector note (how this fires in the shipped tool)

The concrete finding here is **`NoSwallowedNotFoundProphet`** on the `try/catch` (the root cause). The
listed `PreferOptionOverNullProphet` "symptom" is *conceptual* — `displayName(): string` returns a
string and never `return null`s, so no nullable-return symptom prophet fires on it. The downstream
`?-> … ?? 'Guest'` is the laundering this rule is preventing, not a separate emitted finding. Fixing
the cause (let `getById()` throw) removes the whole shape.
