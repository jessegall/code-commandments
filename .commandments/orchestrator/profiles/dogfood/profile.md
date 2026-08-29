# dogfood

Developing code-commandments **under its own orchestration**, so every defect in the
machinery is found by the person who has to fix it rather than reported by somebody
else a day later.

The branch is `main` and the writer role is `integrator`.

## What is different here, and it is not a detail

**Every write belongs to the orchestrator.** This project's own rule: a dispatched
agent holds these disciplines more shallowly, so a delegated edit is how violations
slip in. So the lanes here are *work items*, not worker agents — the board, the
claims and the receipts are exercised against work one agent does in sequence.

That tests everything except the multi-worker path, and it is worth saying which
half is untested rather than implying parity.
