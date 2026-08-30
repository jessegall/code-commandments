# dispatch-announce

<!-- Appended to EVERY dispatch brief, opening and continuing alike. It is a fact about being
     dispatched rather than about the procedure, which is why it does not live in one. Holes:
     {item} {binary}. -->

ANNOUNCE YOURSELF, AT BOTH ENDS. You are a session of your own and nothing else can see you, so
an orchestrator that has to remember to go looking is one that looks late.

`SendMessage` the orchestrator TWICE — no more:

  ARRIVING — one line, now, saying what you have started on.
  LEAVING  — before you stop, saying what you actually DID: the verdict, the count, and where
             the full thing is. "I reviewed 8811721, two findings, report at
             reports/8811721.md" is a leave message; "finished" is not.

The leave message is the one that matters. Work that finishes and tells nobody is work the
orchestrator believes is still running.

If `SendMessage` is not available to you, say so rather than going quiet, and file it instead:

  {binary} build report {item} --ran="<the check you ran>"

Then stop. If more work is queued for you it will be handed over automatically, so what you
learn now is worth keeping for it.
