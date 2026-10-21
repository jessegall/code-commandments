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

## The alias, once the manifest can name the journal that takes it

Not in `journal-plugin.json` yet, and for one reason: the journal's manifest reader refuses a key it
does not know. The moment `aliases` goes in, that release of the plugin cannot be installed on a
journal older than the one that knows the key. What protects against that is the manifest's own
`journal` field, which the journal reads as a minimum: an older one never picks a tag asking for a
newer journal, and keeps the release it has.

Aliases ship in the journal's 4.5.0, so the block goes in with `"journal": "4.5.0"` once that release
is out. Not before: on 4.4.x the minimum is read through the same reader that refuses the unknown key,
so a journal still on 4.4.x would show a red card every time it checks for plugin upgrades, and a
fresh install of our newest release would fail with `keys the journal does not know: aliases` rather
than a word about versions. 4.5.0 reads the minimum first and says plainly what it needs. Waiting
costs nothing, since the alias cannot run on 4.4.x anyway.

The key is `aliases`: a list whose entries each carry a name, the line (relative to the plugin's
folder, holding `{args}`), its `env`, the arguments it passes through, its `reader` and a
`description`. An alias whose name the journal already holds is skipped with a line naming it, and the
rest of the plugin installs:

```json
"aliases": [
    {
        "name": "sins",
        "line": "bin/commandments-release judge {args}",
        "env": {"COMMANDMENTS_PLAIN_PROGRESS": "1"},
        "args": ["--changes", "--branch", "<path>"],
        "reader": "judge",
        "description": "Judge the project against its commandments and show each sin as it is found"
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
