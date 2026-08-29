# AGENTS

<!-- BEGIN: code-commandments briefing (auto-generated, run `composer update`) -->
## Skills — load before you work

Code style in this project lives in the code-commandments skills, published to
`.agents/skills/` and loaded **by the exact id in each bullet below** —
`commandments-backend-<name>` / `commandments-frontend-<name>` (e.g.
`commandments-backend-absence`). HOW you load one is your own business: some agents
have a tool for it, some a `/`-command, some want the file read. The id is the same
either way, and so is the rule — read the relevant skill before writing or reviewing
code. Two tiers.

### ⚠️ THE MOST IMPORTANT RULE — TRACE TO THE SOURCE

**Every sin has an origin. Fix it THERE, never where it surfaces.** A finding is a
symptom; before you change a line, trace upstream to where the bad value, missing
type, or wrong shape is BORN, and fix it at that origin. Then the symptom — and
usually several others you hadn't found — disappears on its own.

This is the rule the detectors exist to serve. Honour it on EVERY fix:

- Do NOT silence the symptom. A `?? default`, a cast, a null-check, a wrapper, a
  constructor override, a try/catch, or removing a type to satisfy a detector is
  laundering the problem, not solving it — and it grows the code while hiding the
  smell. That is the wrong move even when the real fix is awkward to reach.
- Ask "where does this value/shape come from?" and walk back until you reach the
  birthplace. Fix is at the birthplace. If the honest fix touches many call sites,
  touch them — that breadth IS the bug surfacing, not a reason to wrap it.
- If a finding is a genuine false positive, say so and why — and **report it** so
  it gets fixed upstream instead of silently ignored, ALWAYS referencing the code:
  `vendor/bin/commandments report --detector=NAME --reason="…" --ref=PATH:LINE`
  (repeat `--ref` for EVERY file involved — a bug spanning files references each).
  Design-smell detectors REQUIRE a `--best-design="…"`: the cleanest design you can
  conceive for this code. It is the litmus — a report is valid ONLY if the flagged
  code already IS that design; if you can name anything cleaner, that design is the
  owed fix, so implement it and do not file. The reason may not hedge; a "correct,
  but…" is rejected. Never rationalise a real finding as "pre-existing baseline."
- **A report is NOT a deferral.** A detector-report claims ONE thing: the flagged
  code is CORRECT under the architecture and the detector is wrong. If the finding
  is right and an honest fix exists, you MUST implement it — however far it
  cascades: many call sites, a data migration, a refactor that ripples. That
  breadth IS the point; it is the debt surfacing, not a reason to file. The moment
  your report would contain "the honest fix is real, but…" — stop: everything
  after "but" is scope you own. Cost, timing, "needs its own change", or "risks
  breaking existing data" are never grounds for a report; they are the work.

**You are ENCOURAGED to make the tool better — not just when a finding is clearly
wrong.** Surface every improvement idea: a rule that's missing or should catch more,
a false positive, OR a `repent` auto-fix that did the wrong thing or left a rough
edge. Two channels, and using them is expected, not exceptional:

- `vendor/bin/commandments report --reason="…" --ref=PATH:LINE [--ref=…]` — a bug or
  false positive (a wrong finding, or a broken/incorrect `repent` result). A broken
  auto-fix is itself a bug: report it, referencing both the source and the bad output.
- `vendor/bin/commandments feature-request --title="…" --reason="…"` — a new or
  changed rule.

Reporting false positives, flagging bad auto-fixes, and requesting rules is how the
disciplines get sharper — do it whenever something is wrong or could be better, don't
just work around it.

**Frozen files — a file that is deliberately immutable.** A few files must not
change even though they carry sins: a frozen graph migration whose body mirrors
its siblings on purpose, a snapshot committed for the record, generated code
checked into the tree. Mark such a file frozen:
`vendor/bin/commandments freeze <path>` (or add `#[Frozen]` / an `@frozen`
docblock tag by hand). A frozen file is still **scanned** — the call graph,
provenance and type resolution read it, so cross-file findings elsewhere stay
correct — but it is never a **target**: it is never flagged, and a repenter
never rewrites it (a cross-file fix whose edits would touch a frozen file is
dropped whole, never half-applied). Lift it with
`vendor/bin/commandments unfreeze <path>`. **Freeze only what is genuinely
immutable — never to silence a sin you could fix.** A real finding you disagree
with is a `report`; a rule you want off is `disable`; freezing is for files that
by their nature cannot move.

**The journal — how a session survives its own compaction.** A compaction keeps what was
DONE and loses what was DECIDED: the ruling the user gave once, the approach you abandoned,
the work you were half-way through. The transcript on disk lost none of it, so read it back
rather than working from the summary — **after every compaction, before you touch anything**:

  `vendor/bin/commandments journal --back=1` — the stretch the summary replaced
  `vendor/bin/commandments journal user` — only the user's own words, in full

**Declare your work before you change anything**, and close it when it is done — this is
enforced, and a write is refused while nothing stands open:

  `[!start] making Drilldown a composition` … `[!end] making Drilldown a composition`

A `[!start]` with no `[!end]` is unfinished work, which is the one thing a compaction cannot
reconstruct. Tag what your messages carry — `[!start]`, `[!end]`, `[!discovery]`,
`[!correction]`, `[!blocked]` — because through a stretch where you work alone those are the
ONLY messages kept. And when something genuinely must not be lost, do not merely say it:
`vendor/bin/commandments journal remember "<the fact>"` outlives every compaction and is
written into the summariser's own instructions. `vendor/bin/commandments journal
instructions` is the whole brief; load the `commandments-journal` skill for the discipline.

**Running a build with WORKERS — the orchestration mode.** When you are dispatching agents
rather than writing code, `commandments build` is the board: who holds which piece of work,
and what is waiting on YOU. It needs nothing declared — an item is a string and a holder is
a string:

  `commandments build claim <item> --by=<who>` — refused if somebody already holds it
  `commandments build report <item> --ran="<the check>"` — the TOOL runs it, so the number
    filed is the one a process returned rather than one you typed
  `commandments build accept <item>` — settle it and free the hold

A worker's report is its WORDS; a receipt is what a tool READ. Never let a claim become a
fact by being repeated, and remember a lane's honest number is wrong for the branch the
moment its base predates the last merge. Prefer two workers running: a slot is a claim on
your attention, and the third one's cost is paid by the other two waiting on you. Load the
`commandments-orchestration` skill for the discipline — it carries what no refusal can
enforce.

**Stop conditions — when the user says "keep going until X".** Record it at once:
`vendor/bin/commandments stop-condition "<condition>"`. While it stands you may not stop: every
stop is held and sends you back to VERIFY the condition — actually run the command and
read the output, never assume it holds because you did the work. Strike one off with
`vendor/bin/commandments stop-condition met <n>` once it genuinely holds; if you are truly
blocked, `vendor/bin/commandments stop-condition stuck` hands back to the user while keeping the
condition in force. Never `stop-condition clear` to escape a condition you simply haven't met.

**The same gate is where mid-work interjections go.** When the user speaks while you are
already working, decide what their message IS: **steering** the work in hand (a correction,
a change of approach, "while you're in there…") is done **now** — parking it is a way of not
doing it. A **separate task**, one they deferred ("later", "when you're done"), or anything
that would derail the phase you're on is **parked**: `vendor/bin/commandments stop-condition "<the
task, as a statement you can verify>"`, then carry on with what you were doing. Unsure?
Cheap and inside the current phase → do it; opens a new front → park it. A plan takes
precedence over the gate, so parked work surfaces at `plan done` — exactly "later".
Load the `commandments-stop-condition` skill for the discipline.

When in doubt, load `commandments-backend-fix-at-the-source` and re-read it. It is the parent move
behind every other skill.

**Leave it cleaner than you found it — the gentleman's duty.** When you touch a
file (or even read past a sin while working in it), fix it at the source per the
rule above. Every finding on code you come across is yours to resolve.

**The disciplines.** Each one is a skill, and each says in its own description WHEN to
reach for it — the syntax you are about to write, the decision you are about to make. Load
one when its subject comes up, and load it again rather than working from memory: a
compaction drops instructions silently while leaving you the impression they are still
there. A `judge` finding names the skill that fixes it, so you are never guessing.

Load **`commandments-backend-fix-at-the-source`** before your first edit whatever else you
do — every other discipline defers to it. And **`commandments`** is this whole list as a
loadable skill, for when you want the map in front of you.

**CONSTANTLY IN PLAY — these fire on ordinary, everyday code, so you will reach for them
most:**

- **`commandments-backend-fix-at-the-source`** — the root-cause-first move: trace a value to where it's born, never patch the symptom. Governs how every change is made.
- **`commandments-backend-guard-clauses-and-flow`** — validate preconditions at the TOP (early return/throw), flat body, happy path last; never bury a check inline.
- **`commandments-backend-value-objects`** — give related data a type: no loose `array<string,mixed>` bags, no data clumps, no primitive obsession. (Decide the type; then `spatie-data` is how to write it.)
- **`commandments-backend-spatie-data`** — how to write and construct Spatie `Data` classes — `::from()` not `new`, total types, sealed and readonly.
- **`commandments-backend-spatie-data-hydration`** — construct and consume `Data` objects without re-doing what the class declares — pass raw input, let `::from`/casts/collections build.
- **`commandments-backend-laravel-idioms`** — typed request/bag access (never raw `->input()`/`->get()`), required constructor DI (never `app()`/facade), Eloquent scopes + intention-revealing model mutation methods.
- **`commandments-backend-page-objects`** — the composed `Data` a controller returns for a page — container-injected + `#[Hidden]` collaborators, computed slots over a fat constructor, transformers for output shape.
- **`commandments-backend-documentation`** — concise, present-tense docs; rare inline comments; never narrate the past.
- **`commandments-backend-absence`** — modelling a value that might be missing (`?T`, `Option`, `null`, empty, Null Object, throw).
- **`commandments-backend-class-layout`** — state at the top — traits, constants, properties and hooks above the constructor, methods after.
- **`commandments-backend-method-mood`** — commands are imperatives (`hide()`), state predicates are questions (`isHidden()`).
- **`commandments-backend-type-honesty`** — a type must not lie: don't fake optionality — a `?T` the design always has set, then defended with `?->`/`?? <fake>` or stashed as save/restore scratch state. Make the type certain (pass it, hold it non-nullable, a per-call value object). The complement of `absence`.
- **`commandments-backend-route-actions`** — route actions are thin, single entry points — no controller wrapping another, no duplicate actions, no two routes to one action.
- **`commandments-backend-repeated-call-helper`** — a repeated `with`-style call passing the same named argument belongs as a named method on the receiver's type.

**ON CONTACT — load the moment the work touches the subject:**

- **`commandments-backend-exceptions`** — throwing or catching: named `::for()` factory exceptions, never swallow a failure.
- **`commandments-backend-enums-with-behaviour`** — a closed set of values: seal it as a native backed enum, put the per-case logic on the enum (not a `match` at every call site).
- **`commandments-backend-role-vocabulary`** — a keyed store / membership set / first-match dispatcher: name it `*Registry`/`*Set`/`*Resolver`, extend the base, honour the contract.
- **`commandments-backend-tell-dont-ask`** — behaviour belongs with its data (feature envy): don't exile a loop over one object's collection into a separate class — move it onto the object (`$node->edges()`, not `EdgeDetector::detect($node)`). A Strategy over flat scalar fields is the exception.
- **`commandments-backend-pass-the-object`** — demand the resolved type you need, not an id plus its container: a method that takes `(Workflow $workflow, string $nodeId)` then unpacks `$workflow->graph->nodeById($nodeId)` should take the node — the caller resolves once and passes the object (and owns the not-found failure).
- **`commandments-backend-concurrent-state`** — state shared across requests/workers (`::for($id): Concurrent<self>`).
- **`commandments-backend-templates`** — a multi-line string is a heredoc that SHOWS its output, never a list of line fragments joined.
- **`commandments-frontend-vue-components`** — extract a component when template markup REPEATS, or when an element reaches DEEP into nested data — pass it the mid-object as a prop.
- **`commandments-frontend-vue-control-flow`** — dispatch on a value with `<SwitchCase :value>` (a slot per case), never a `v-if`/`v-else-if` chain re-testing the same subject.
- **`commandments-backend-dependency-direction`** — a declared layer may only reference the layers it declared it may use — down the stack, never back up, never sideways; the direction is enforced from the project's own layer declaration.
- **`commandments-backend-behaviour-per-method`** — a parameter that picks WHICH behaviour runs means two methods share one name — split them and let the call site say which it wants, instead of passing a bare `true`.
- **`commandments-frontend-mirrored-server-type`** — a hand-written TS type that mirrors a backend Data class is a duplicated contract — mark the Data class `#[TypeScript]`, generate the type, and import the generated one.
- **`commandments-typescript-absence`** — model absence honestly — one spelling for missing, no `??` that invents a value, no `?.` on something always set.

**Finding and fixing sins — the checklist workflow.** Run
`vendor/bin/commandments judge src` ONCE — and **pass any path** to scope the
scan: judge runs BOTH engines over whatever you point it at, so a path holding
frontend sources (`judge resources/js`) is judged as the frontend
— **Vue components and plain TypeScript alike** — and any subdirectory of your
own tree scopes to that subtree. (Also `--skill=NAME` to scope to one group; `--branch`
for files new/changed vs `main`; `--changes` for uncommitted changes.) A full scan
is slow, so it writes the findings to a checklist — your session's
`.commandments/sessions/<id>/sins.md` (the run prints the exact path) — and
that file, not repeated scans, is how you work:

1. Open the checklist judge wrote. Each line is one sin: `file:line`, the scope, and
   the detector, grouped under the skill that teaches the fix.
2. Go top to bottom, ONE line at a time: read that section's skill, fix the sin at
   the source, then **delete that line from the file.** Do not re-run judge, re-scan,
   or re-verify between fixes — the open checklist is your only source of truth.
3. Work **wave by wave.** When the file is EMPTY, run judge again. If your fixes
   rippled into other files it writes a fresh worklist — a new wave; work it the same
   way. Repeat, judging ONLY between waves, until a run is clean and deletes the file.

**Don't know what a finding MEANS? Ask.** A checklist line names its detector and
nothing else, so when you do not recognise a rule — or are about to argue with one —
run `vendor/bin/commandments info <sin>` before you touch the code. It prints what
the rule flags, WHY it is a sin in the skill's own words, how it is fixed, a worked
example, and the exact commands that act on it (fix it, find it, turn it off, report
it). The name is matched leniently, so the detector name straight off the checklist
works: `info ArrayBagDetector`, `info array-bag`, `info ArrayBag`. Add `--full` for
the skill's whole principle. Guessing at a rule you have not read is how a finding
gets "fixed" by silencing it.

**Auto-fixable sins.** Some sins have a scribe that fixes them. The report
advertises the command — typically `vendor/bin/commandments repent --repent=latest`
(optionally `--sin=NAME` to fix just one). `--repent=latest` scopes repent to the
last judge run's checklist, so it fixes exactly what was reported; review the diff
with `--dry-run` first.

**Write commandments of your OWN.** The shipped rules are not the ceiling. When
this project has a discipline of its own — a convention you keep restating in
review, a mistake that keeps coming back, anything the shipped set doesn't
catch — it can become a rule that judges every file from then on. Scaffold it:

`vendor/bin/commandments make <Name>` (add `--engine=frontend` for a rule over
your frontend sources — a Vue component or a TypeScript module)

That writes the three classes a commandment is made of — the skill that teaches
it, the sin that names it, the detector that finds it — into
`.commandments/custom/`, registers the detector in this project's config, and
prints the rest of the process. The folder is committed like any other source:
these are the project's rules. **Load the `commandments-writing-detectors`
skill before you write one** — it lists the engine predicates that already
exist (hand-rolling one that does is the usual first mistake) and teaches the
probe-then-calibrate discipline that proves a detector fires on what you meant.
Reach for this whenever the user asks for a new rule, check, or detector.

**Scaffoldable sins.** A few sins are fixed by reaching for a generic helper the
project may not have yet (e.g. a no-op invokable for a nullable callback). For
those the report advertises `vendor/bin/commandments scaffold --sin=NAME`, which
generates the helper into your source root with its namespace set. Scaffold the
construct, then write the fix that uses it (`scaffold` creates the helper; `repent`
fixes call sites).
<!-- END: code-commandments briefing -->
