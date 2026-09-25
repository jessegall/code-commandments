A failure is information. The instant it happens, it knows the most it will ever know — *what* broke and
*with what values*. Throw that knowledge **loudly, by type, at the source**. Every line you put between
the failure and its surfacing — a `catch` that returns null, a default that papers over it, a bare
`Exception("...")` — destroys information and moves the eventual debugging session further from the cause.

This is [`fix-at-the-source`](../fix-at-the-source/SKILL.md) for the error channel — and the place the
[`absence`](../absence/SKILL.md) skill sends you when "missing" turns out to be a broken state.

### The one place you tolerate: a named outer boundary

Fail-hard does **not** mean every layer rethrows forever. It means failures travel *up* to **one explicit
boundary** that is allowed to absorb them — and even there, absorbing is **observable**, never silent. The
canonical shape: an untrusted-input decoder that catches per item, **logs**, and skips, then fails hard if
*nothing* survived.

Inside the system, invariants throw. At the *one* untrusted edge, you catch-log-skip. That's fail-hard
*and* resilient — not graceful-and-silent.