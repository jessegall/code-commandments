# running the slate binary instead of the session's poisons every future session

MEASURED (story 13): a command run through the slate's own bin writes bookkeeping into the slate, and SPEC 1 says the slate must never become stateful because every later copy inherits it. Silent, and it propagates. Either the slate refuses to act as a session, or a command run there says which session it means.

- queued 2026-08-30 17:54
- done 2026-08-30 18:09 — the slate refuses every action but setup/help/init/next; having an engine/ is what tells slate from session
