---
name: journal-lean
primary: true
description: Write the least code that solves the whole problem: search for what already does the job, extend its owner, route through the funnel, and say what was skipped.
commands: lean
---

# Lean code, reused

Law L7: write the least code that solves the whole problem, find what already does it and reuse it, and never write the same logic twice. The least code is the best code. A second copy of anything is a decision made twice, and the two drift apart.

Before the first line of a change:

1. **Search first.** Grep for the verb, the noun and the file or key the work touches. Something here usually does the job already, under another name.
2. **Extend the owner.** If it does most of the job, add a parameter or a method to the type that owns the data. Do not write a sibling beside it.
3. **Route through the funnel.** Every kind of operation has one funnel: one place that reads sessions, one that writes a row, one that formats a message. Call it. If a caller reads or writes around it, send that caller through it too.
4. **Write only what is left.** Delete what the new code replaces. No wrapper, flag or fallback for a case that cannot happen.
5. **Say what was skipped.** When part of the problem is left undone, or a copy you found was left in place, say so in the report with its file and line, and file a to-do for it. Never report the whole problem solved when it was not.

Read the diff before every commit as a reviewer would: any body written twice, any dead line, any name the codebase does not use is fixed now.
