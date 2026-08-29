# restrictions

**Brief a worker from the profile, never from memory.** A dispatched agent holds
this project's context shallowly, so what it is not told, it does not know. Point it
at `orchestrate show <profile>` — the traps are the half that has already been paid
for, and a fresh agent reading them catches in its first minute what its predecessor
learned the hard way.

**Never notify another agent about a fix before it is deployed** — committed, tagged
AND pushed. A peer told about an unreleased version goes looking for something it
cannot install, and its probes then test the old binary.

**Never sleep-poll a background task.** The harness re-invokes on completion. A poll
loop burns wall clock, inflates the work counter, and — because `Stop` only fires when
a turn ends — silences every gate for as long as it runs.

**Never accept an item whose receipt says COULD NOT MEASURE.** That is not a green.
