# The readers the plugin ships, and the journal commands that use them

A reader picks the lines of a command's output worth showing while it runs. The journal reads one
from a `<name>.pattern` file: its first line says what it picks, its second is the regular expression
that picks it, and `key: value` lines follow — `progress:`, whose groups are a count and a total,
`label:`, the words the card is titled with, `commands:`, the commands a background run is watched
through even when nothing names the reader, and `finished:` and `failed:`, the lines a card settles
on once the run ends.

## `judge.pattern`

Reads `commandments judge`, which the `sins` alias runs.

- **Picks** a sin said plainly (`typed_lines.py, line 52: oldest_line_at, a sentence used as a method
  name`), the count the run ends on (`4 sins in 3 files`), the clean verdict (`✓ No sins found in 1181
  files.`), where the checklist was written, and a run that could not read everything (`⚠ 10 file(s)
  left unread`). A run a journal watches says what it found in words rather than printing the report a
  terminal gets, so there is no path, no node kind and no detector class in a line, and the teaching
  prose is left where it belongs, in the skill the checklist names.
- **Counts** `parsing`, `reading` and `detector` steps. Those are the words the counted form prints;
  `judging` never appears, because a judging step is one detector and the line says so.
- **Settles** on the count and the checklist path, so a finished run's card says what it found and
  where the work is rather than whichever finding came last. A run that could not read everything
  settles on the line saying so.
- The output a journal command reads is not a terminal, so it carries no colour and the patterns need
  no escape sequences.

## The alias

`journal-plugin.json` names the `sins` alias, which runs `bin/commandments-release judge {args}` with
this reader. Two things it needs, both of them already in that entry:

- **The counted lines have to be asked for.** The judge draws a bar on a terminal and says nothing
  into a pipe, so that a piped run never fills a log with counting. It writes the counted lines when a
  journal check names its report (`JOURNAL_REPORT`) or when `COMMANDMENTS_PLAIN_PROGRESS` is set,
  which the alias's `env` sets. If a command's line is run through a shell rather than exec'd, the
  prefix form works instead: `COMMANDMENTS_PLAIN_PROGRESS=1 bin/commandments-release judge {args}`.
- **The progress lines go to stderr**, and the findings to stdout, so a reader that watches only
  stdout shows findings and no progress.

The entry also carries `"background": true`, which moves the run to the background the moment it starts
and watches it through this reader. A judge of a whole tree is slow enough that holding the agent's
turn is the wrong behaviour, and a backgrounded run settles its card on the count and the checklist
path, which is what the agent needs from it.

The name is ours to choose, and `journal sins --changes` says what it gives better than the package's
own name would. A run with no argument judges the whole tree, which is slow on a large one;
`--changes`, `--branch` and a path are the scoped forms, and the checklist a run writes is what the
agent then works through.

The manifest's `journal` field is what keeps an older journal from taking a release whose keys it
cannot read: it is the minimum version, and a journal below it keeps the release it has. `background`
is read by journal 4.53.0 and refused by anything older, so the minimum says 4.53.0, and a project on
an older journal stays on the release it has until it updates.
