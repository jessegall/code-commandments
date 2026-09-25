---
name: commandments-writing-detectors
description: "How to write a commandment of your OWN — a project-local rule that judges every file from then on. Read this BEFORE writing or changing a rule in `.commandments/custom/`, when the user asks for a new rule/check/detector, when `commandments make` points you here, or when you are about to reach for a regex to inspect code."
---

# Writing your own commandments

> The shipped rules are not the ceiling. A convention you keep restating in review is a rule you
> haven't written yet. Write it once, and it judges every file from then on.

## A rule is a question the engine answers

A rule of your own is data the binary runs — a `.json` file in `.commandments/custom/` — and it asks
the same engine every shipped rule asks. It names the engine it judges, the sin it finds, and a query:
a **selector** that opens it, then **`where`** steps that keep a node and **`reject`** steps that drop
one. **Each step makes exactly one check**; a rule is only as good as the question each step asks.

```json
{
    "engine": "backend",
    "sin": {
        "name": "raw-sql",
        "description": "SQL written inline at a call site.",
        "rule": "Queries go through the repository that owns the table.",
        "skill": "no-raw-sql"
    },
    "find": {
        "select": "call",
        "where": [
            {"resolves": "Illuminate\\Support\\Facades\\DB", "of": "child:class"},
            {"nameIn": ["select", "statement", "unprepared"]}
        ],
        "reject": [
            {"file": "*Repository.php"}
        ]
    }
}
```

**The selector** is a neutral kind, which reads the same in every language — `call`, `construction`,
`function`, `type-declaration`, `assignment`, `loop`, `branch`, `return`, `throw`, `catch`,
`comparison`, `literal`, `member-access`, `null-safe`, `identifier`, `import`, `parameter`, `block`,
`bail-out`, `expression-statement`, `self-reference` — or `kind:<Kind>`, a language's own node kind
(`kind:Expr_MethodCall`) when no neutral kind says it.

**A step's one check:**

| Check | Keeps the node when |
|---|---|
| `"is": "loop"` | it answers the neutral kind |
| `"kind": "Expr_StaticCall"` | it is the language's own kind |
| `"name": "select"` / `"nameIn": [...]` | its name — a call's or an access's is its name child's — is it |
| `"text": "..."` | a literal's text is exactly it |
| `"resolves": "App\\Models\\User"` | what its name refers to, resolved, is that |
| `"hasModifier": "static"` / `"hasFlag": "..."` | it carries it |
| `"withinLoop": true` | it sits inside a loop (or not, with `false`) |
| `"documented": false` | it carries a doc comment (or not) |
| `"file": "*Repository.php"` | its file, or any tail of the path, matches the glob |
| `"descendant": {step}` | some node inside it passes the step |

Any step can judge a **related** node instead with `"of"`: `parent`, `enclosingFunction`,
`enclosingType`, or `child:<field>`.

A rule the binary cannot run says why when `judge` starts — the file, and which part is wrong.

## Scaffold it — don't hand-write the plumbing

```bash
vendor/bin/commandments make NoRawSql                    # a backend rule and a skill of its own
vendor/bin/commandments make NoRawSql --engine=python     # another engine
vendor/bin/commandments make NoRawSql --skill=absence     # taught by a skill that already exists
```

That writes `NoRawSqlDetector.json` and, when the skill is new, `skills/no-raw-sql/SKILL.md` into
`.commandments/custom/`, turns the rule on under `detectors` in `.commandments/config.json`, and prints
the rest of the process. The folder is kept out of the `.commandments/` gitignore: they are your rules,
so commit them.

They are named as yours wherever they fire: `judge` prints `[NoRawSqlDetector (custom)]` in the
console and in `sins.md`, `judge --list` lists them so, and `report --detector` refuses them — nothing
upstream can fix a rule the package does not ship.

## The anatomy — a rule, a sin, a skill

**The skill teaches.** `skills/<slug>/SKILL.md` is what a finding sends the reader to, published as you
write it into the project's skill library as `commandments-<slug>`. Its front matter says when to load
it (`description`), what the briefing lists (`summary`), how often it comes up (`tier: mandatory` or
`keep-in-mind`) and which languages it teaches (`languages: [php]`).

**The sin names.** Inside the rule: a `name` (the `--sin=` id), the `skill` it points at by slug — your
own, or a shipped one such as `backend/absence` — the `description` (the symptom) and the `rule` (the
positive directive the fix follows).

**The query finds.** Nothing else. It has no fix logic, and it knows nothing about the report.

## Classify by what the code IS, never by what it is CALLED

The cardinal rule. Derive the answer from what a node is and what its name resolves to — the neutral
kind, the language's kind, the class a call reaches. **Never** from a class, method or variable name, a
suffix, or a hardcoded list. `name`, `nameIn` and `file` exist because a call has to be named at some
point; reach for them last, and never as a list of the exceptions you happened to meet. Names lie, and a
rule built on one fires on the wrong code the first time somebody renames well.

## Prove it fires — a rule nobody has watched is a guess

A rule that parses is not a rule that works. **Write a probe**: a throwaway file under a scanned path
holding one example of every form you mean to catch **plus the near-misses you must NOT catch**.

```php
// src/ProbeNoRawSql.php  — delete after
DB::select('select * from orders');          // must fire
DB::unprepared($sql);                        // must fire
Order::query()->where('id', $id)->get();     // must NOT fire — the query builder
$this->select($columns);                     // must NOT fire — not the DB facade
```

```bash
vendor/bin/commandments judge src --sin=<your-sin> --no-checklist
```

Confirm **exactly** the intended lines are flagged — not "some lines". Then delete the probe. The
near-misses are the whole point: a rule that fires on everything is not a rule.

## Calibrate on real code — before you trust it

A green probe proves it *can* fire; it does not prove it is *right*. Run it over your actual source —
scoped with `--changes` or `--branch` — and **read the hits by eye**, judging each **against the skill**,
never against what the code happens to do today.

- **Volume proves nothing.** 400 hits can be 400 real sins. A widespread pattern is not "convention"
  that excuses a finding. Do not soften a rule because it fires a lot.
- **Only a genuine false positive invalidates it** — code that is *correct under your architecture* yet
  gets flagged. Then tighten with a **principled `reject`** (a check on what the code is), never a name
  list.
- **Some ideas die here.** If no check separates the sin from a legitimately valid look-alike — if the
  difference is only the author's intent — the rule is not viable. Cut it. That is a successful
  outcome, not a failure.

## It does not auto-fix

A rule of your own reports; it never rewrites. If the honest fix depends on what the code MEANS —
which value object to introduce, where absence really belongs — an auto-fix would launder the problem
instead of solving it. Let the skill teach the reader.

## The cadence, in order

1. **Load this skill** — before writing a line.
2. `commandments make <Name>` — scaffold the rule, and the skill when it is new.
3. **Write the teaching first.** If you can't state what good looks like, the rule doesn't know what
   it's looking for either.
4. Name the sin: the symptom, and the rule as a positive directive.
5. Write the query — a selector, then one check per step.
6. **Probe it.** Every form you mean to catch, plus the near-misses.
7. **Calibrate** on real code. Tighten, or cut.
8. `vendor/bin/commandments sync` — publishes your skill so the agent can load what the finding points
   at.

## Reference

<!-- BEGIN: commands:make (auto-generated, run `composer sins`) -->
| Command | Does |
|---|---|
| `commandments make <Name>` | scaffold a backend (PHP) commandment and turn it on |
| `commandments make <Name> --engine=frontend` | scaffold a frontend (Vue) one instead |
| `commandments make <Name> --engine=typescript` | scaffold a TypeScript one instead |
| `commandments make <Name> --engine=python` | scaffold a Python one instead |
| `commandments make <Name> --engine=csharp` | scaffold a C# one instead |
| `commandments make <Name> --skill=NAME` | point the sin at an EXISTING skill (shipped or your own) instead of writing a new one |

<!-- END: commands:make -->
