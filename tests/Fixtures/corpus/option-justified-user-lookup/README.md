# option-justified-user-lookup

**Verdict: `Option<User>` is the better design here.** The producer
(`UserDirectory::findByEmail`) is consumed by THREE callers that each treat
absence as a *different* thing, and that fan-out is exactly the condition under
which forcing explicit handling pays for itself.

## The scenario

`UserDirectory::findByEmail()` resolves a user-ish entity. Three actions consume
the result, each with its own absence policy:

1. `AssignReviewerAction` — absence is fine, **default** to a shared inbox.
2. `InviteCollaboratorAction` — absence is a **domain error**, raise.
3. `NotifyOnboardingAction` — **thread** the value into a follow-up step
   (`gateway->sendWelcome`), and only when present *and* active.

## Why Option wins at THESE call sites

- **Three different absence policies, one producer.** Because each caller does
  something distinct with "missing", the handling lives at the call site, not in
  the helper. With a bare `?User` there is nothing forcing each caller to handle
  the null — caller #2's guard is *load-bearing* (a missing guard leaks a `null`
  into code typed `User`), yet the nullable signature makes that guard optional.
  `Option` makes it unrepresentable to use the value without deciding:
  `getOrElse`, `getOrThrow`, `map`/`filter`/`getOrElse`.
- **One caller threads the value through transforms.** `NotifyOnboardingAction`
  is the tell: with the nullable it becomes a guard pyramid (null-check, then
  active-check, then call). With `Option` it is one chain —
  `filter(active)->map(sendWelcome)->getOrElse(false)` — no intermediate null
  variable to misuse.
- **Absence is a real domain event, not "unset".** The invite flow turns absence
  into an exception; the default flow turns it into a fallback. That divergence
  is the signal that absence deserves a first-class type rather than `null`
  re-coalesced three different ways.

## What would have flipped the verdict

If there were a single local caller doing one clean `?-> ?? default`, or the
helper were thin/private with absence meaning merely "not set", `Option` would be
pointless ceremony and the nullable would be correct. It is the **multiple
callers with divergent policies + one transform-threading site** that earn it
here.

- `golden/` — `Option<User>`; each call site decides cleanly (`getOrElse` /
  `getOrThrow` / `filter`+`map`+`getOrElse`).
- `messy/` — bare `?User`; the optional-by-default guards and the guard pyramid
  the Option removes.
