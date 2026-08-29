# restrictions

**Ship every landed feature or fix, then tell the far side.** Commit, tag, push — and
only THEN notify the workflows agent, with the version that is now installable. No
batching separate features into one release.

**Never notify a peer before a fix is deployed.** A peer pointed at a version it
cannot install goes looking for it, and its probes then test the old binary. That is
how a working fix got reported broken twice.

**Never sleep-poll a background task.** The harness re-invokes on completion. A poll
loop burns wall clock, inflates the work counter, and — because `Stop` only fires
when a turn ends — silences every gate for as long as it runs.

**Never accept an item whose receipt says COULD NOT MEASURE.** That is not a green.

**Only the holder can say a hold is finished.** Accept after the worker's own report;
`orphan` only when it is demonstrably unreachable, and with liveness honestly absent
that means pinged and silent, not merely quiet. Both failures on this build were the
same shape: settling an item from the settler's vantage point rather than the
worker's — green-therefore-done, and silent-therefore-gone.

**Brief a worker from the profile, never from memory.** A dispatched agent holds this
project's context shallowly, so what it is not told, it does not know. Point it at
`orchestrate show <profile>`; the traps are the half already paid for, and a fresh
agent reading them catches in its first minute what its predecessor learned the hard
way.

**A brief quotes a command, not a number.** `lane list`, not "all four on v4.284.0".
The briefing is the one artifact nobody inspects.
