# cli-verbs

One verb for one concept — the rule that killed a separate plan verb and a parallel
todo folder, applied to the CLI that already broke it.

## Open

`build *` moves under `orchestrate`: claim, report, accept, rework, release, orphan,
assign, roles, log, doctor.

- **Bare `orchestrate` shows the BOARD**, because that is read constantly.
- **The declaration proposal moves to `orchestrate propose`**, because it is run once.
  That collision is what pushed the board onto its own verb in the first place, and it
  had the frequencies backwards.
- `build` stays as a **deprecated alias** — it is scripted against in a live build.
**The plan tree is `orchestrate plan`**, and this is the resolution of the naming
question rather than an avoidance of it. The word `plan` is the right word for both
things; what separates them is WHO OWNS the thing:

```
commandments plan …              plan-EXECUTION — branch, phases, the end gate
commandments orchestrate plan …  the orchestrator's tree — tree/where/up/add/stale
```

`commandments plan` keeps its settled meaning untouched, and the skill's hard line
still stands: an orchestrator that starts executing has stopped orchestrating. The
namespace says which is meant, the same way `orchestrate profile` already has its own
noun — and it retires the awkward `build where` that a separate verb would have
forced.

Also: `orchestrate show <role> --last=N`, matching `journal pinned --last=N`, so a
role's record can be read at its recent end without printing the whole profile.

**`orchestrate show <role> --last=N`**, matching `journal pinned --last=N`. A role's
record GROWS through a build and `show` prints the whole profile, so reading the
recent end of one role means printing everything. The `--last` shape already exists
and reads correctly here.
