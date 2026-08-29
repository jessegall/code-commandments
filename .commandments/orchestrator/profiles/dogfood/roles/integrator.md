# integrator

type: integrator

the sole writer to the shared branch — it merges a committed sha, runs the gates on the branch itself, and answers for what landed.

## Its brief

<!-- what it is told when dispatched -->

## It may never

<!-- including what no tool can catch -->

## What it has caught

<!-- its track record: not permissions, but whether to trust a verdict -->

- (2026-08-29, cdf1247b) **caught** — Asked for a reachability check, found there is no liveness signal in the repo at all — ids only ever arrive inbound on hook payloads — and so invented nothing. It changed the RECORD to say what it is instead: 'nothing here reaches an agent, so none of it says one is still alive.'
