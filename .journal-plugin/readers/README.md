# The readers the plugin ships, and the journal commands that use them

A reader picks the lines of a command's output worth showing while it runs. The journal reads one
from a `<name>.pattern` file: its first line says what it picks, its second is the regular expression
that picks it, and `progress:` and `label:` lines may follow, the progress groups being a count and a
total.

## `judge.pattern`

Reads `commandments judge`:

- **Picks** a finding (`  path/to/File.php:34  …  [SomeDetector]`), the skill heading each group sits
  under (`backend/absence  (3)`), the count the run ends on (`7 sins across 3 skills.`) and the clean
  verdict (`✓ No sins found in 1181 files.`). The teaching prose between them is left out: it is the
  same every run and the agent reads it from the skill.
- **Counts** `parsing`, `reading` and `detector` steps. Those are the words the counted form prints;
  `judging` never appears, because a judging step is one detector and the line says so.
- The output a journal command reads is not a terminal, so it carries no colour and the patterns need
  no escape sequences.

## The entry, once the journal takes one

Not in `journal-plugin.json` yet, on purpose: the journal refuses a manifest key it does not know, so
adding it before the journal accepts one would fail every install. The key is `aliases` (agent-journal task
1237, not built yet): a list whose entries each carry a name, the line, its environment, the arguments
it passes through and its reader. An alias whose name the journal already holds is skipped with a line
naming it, and the rest of the plugin installs:

```json
"aliases": [
    {
        "name": "sins",
        "line": "bin/commandments-release judge {args}",
        "env": {"COMMANDMENTS_PLAIN_PROGRESS": "1"},
        "args": ["--changes", "--branch", "<path>"],
        "reader": "judge"
    }
]
```

The name is ours to choose, and `journal sins --changes` says what it gives better than the package's
own name would.

Two things it needs from the journal's side:

- **The counted lines have to be asked for.** The judge draws a bar on a terminal and says nothing
  into a pipe, so that a piped run never fills a log with counting. It writes the counted lines when
  a journal check names its report (`JOURNAL_REPORT`) or when `COMMANDMENTS_PLAIN_PROGRESS` is set.
  Hence the `env` key above. If a command's line is run through a shell rather than exec'd, the
  prefix form works instead: `COMMANDMENTS_PLAIN_PROGRESS=1 bin/commandments-release judge {args}`.
- **The progress lines go to stderr**, and the findings to stdout, so a reader that watches only
  stdout shows findings and no progress.

A run with no argument judges the whole tree, which is slow on a large one; `--changes`, `--branch`
and a path are the scoped forms, and the checklist a run writes is what the agent then works through.
