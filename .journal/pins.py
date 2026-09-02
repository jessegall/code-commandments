"""A fact that must reach the far side of every compaction.

A TAG IS FREE. A PIN IS NOT. A tag rides on a message you were sending anyway; a pin rides
on nothing and is restored, in full, on the far side of every compaction — so it costs
room in the context it lands in, and only the far side can ever spend it.

IT DOES NOT SHAPE THE SUMMARY. The summariser cannot be addressed at all (see
`hook.on_pre_compact`); a pin is handed back AFTER the loss, beside the summary, never
inside it.

AND IT NEVER EVICTS, AND NEVER TRIMS. There is no cap on how many stand and no cap on
how many are handed over: every standing pin and rule reaches the far side, always. The
only limit is on the LENGTH of one entry, enforced when it is written. The tool this
replaces dropped the oldest silently, and the pin it ate — "DO NOT ACT ON status's
Running LIST" — cost a real build two hours of a wrong board. A tier that silently forgets
is the exact failure the whole system exists to prevent, so nothing leaves the store
except by a person striking it, with a reason.

THE TEST, three questions: did somebody DECIDE it; would the next reader get it WRONG
without it; will it still be true tomorrow? A status, a count, or what you just did fails
the third and becomes a confident falsehood wearing the same authority as the facts that
still hold.
"""
from __future__ import annotations

from datetime import datetime, timezone
from pathlib import Path

import state

KEY = "pins"
#: A RULE IS A PIN FOR EVERY TRACK. Pins say what this line of work decided; a rule says
#: what the project decided, and `tracks.switch` never moves it. Same shape, same cap,
#: same citation into the transcript — one more question before writing one: would it
#: be wrong on any OTHER track? If only on this one, it is a pin.
RULES = "rules"


def age(at: str, now: datetime | None = None) -> str:
    """How long this fact has been asserted, in the coarsest honest unit.

    THE ONE PLACE THIS SYSTEM ASKS TO BE TRUSTED. Everything else here fails by being
    absent — an untagged message files nothing, a hook that never runs holds nothing,
    `work.note` refuses rather than guessing. A pin is the exception: it is re-asserted
    verbatim at the top of every compaction, in the highest-authority position the system
    has, and nothing revisits it. `pins.py` states the test in its own docstring — will it
    still be true tomorrow — and then never asks again.

    So the age is SHOWN and nothing is expired. A date invites the question; it does not
    answer it. Automatic eviction is the failure this module exists to prevent, and a
    stale pin you can see beats a true one that silently vanished.

    Unreadable timestamps return "" rather than a guess: a wrong age on a true fact is
    the same confident falsehood the whole exercise is against.
    """
    try:
        when = datetime.fromisoformat((at or "").replace("Z", "+00:00"))
    except ValueError:
        return ""
    if when.tzinfo is None:
        when = when.replace(tzinfo=timezone.utc)
    secs = ((now or datetime.now(timezone.utc)) - when).total_seconds()
    if secs < 3600:
        return "just now"
    if secs < 86400:
        return f"{int(secs // 3600)}h ago"
    return f"{int(secs // 86400)}d ago"


def _all(root: Path, key: str = KEY) -> list[dict]:
    got = state.get(root, key, [])
    return got if isinstance(got, list) else []


def live(root: Path, key: str = KEY) -> list[dict]:
    return [p for p in _all(root, key) if not p.get("struck")]


def add(root: Path, fact: str, at: str, limit: int, supersedes: int | None = None,
        where: dict | None = None, key: str = KEY):
    """Pin a fact. Refuses a paragraph, and records where it was said.

    THE LIMIT IS ON LENGTH, NOT ON COUNT, and that is the whole change. A pin is re-read in
    full at every compaction forever, so what it costs is not a slot — it is the reader's
    attention, every single time. A 380-character pin was three facts and a rationale
    wearing one number; refusing it costs the writer one sentence of thought and saves
    every future reader the paragraph.

    The refusal SHOWS THE OVERFLOW rather than truncating, because a pin silently cut in
    half is a fact that reads as complete and is not.
    """
    fact = " ".join(fact.split())
    if not fact:
        return False, "pin what? one line: the fact, and what makes it matter"
    over = refused(fact, limit)
    if over:
        return False, over
    made = {"fact": fact, "at": at, "struck": None, **(where or {})}
    # THE RECORD IS SHARED, so the load and the save are one held operation. Two sessions
    # pinning at once each loaded eight and each wrote nine, and one pin was gone with
    # nothing to show for it — the silent loss this module's docstring swears against.
    noun = "rule" if key == RULES else "pin"
    with state.locked(root):
        items = _all(root, key)
        if supersedes is not None:
            i = supersedes - 1
            if i < 0 or i >= len(items):
                return False, f"there is no {noun} {supersedes}. `journal {key}` numbers them."
            if items[i].get("struck"):
                return False, f"{noun} {supersedes} is already struck by: {items[i]['struck']}"
            items[i]["struck"] = fact
            items.append({**made, "replaced": supersedes})
            state.put(root, key, items)
            return True, f"{noun} {len(items)}, replacing {supersedes}"
        items.append(made)
        state.put(root, key, items)
        standing = len([p for p in items if not p.get("struck")])
    verb = "ruled" if key == RULES else "pinned"
    return True, f"{verb} {len(items)} ({standing} standing)"


#: Paths that exist for one session. A pin naming one is a citation to nothing: the
#: scratchpad is `/private/tmp/…/<session id>/scratchpad`, a different directory for every
#: session, and the OS clears it besides. Measured: "Reactivity groundwork report is at
#: scratchpad/reactivity-groundwork.md" — the report was real, and no later reader could
#: open it. A report worth a pin belongs in the repo; its claims belong in pins.
VOLATILE = ("scratchpad", "/tmp/", "/private/tmp", "/var/folders/")


def refused(fact: str, limit: int) -> str | None:
    """Why this pin cannot be written, or None. ONE text, said in two places.

    The CLI says it after the command ran and exited 1, which a reader can skim past.
    The PreToolUse gate says it BEFORE the command runs, as a denied tool call, which a
    reader cannot — and the two must be the same words, or the gate and the command would
    disagree about the rule they share.
    """
    fact = " ".join(fact.split())
    low = fact.lower()
    hit = next((v for v in VOLATILE if v in low), None)
    if hit:
        return (
            f"this pin cites {hit!r}, a path that exists for one session only — it will "
            f"point at nothing tomorrow, which fails the test a pin has to pass. Put the "
            f"file in the repo and cite that, or pin its CLAIMS instead of its location."
        )
    if not limit or len(fact) <= limit:
        return None
    return (
        f"{len(fact)} characters, and a pin has {limit}. This is re-read in full at "
        f"every compaction, so it has to be a CLAIM, not the reasoning behind it:\n"
        f"  keep  …{fact[:limit - 20]}\n"
        f"  cut   …{fact[limit - 20:][:120]}\n"
        "The reasoning is already in the transcript. Pin the claim; "
        "`journal pins <n> --full` reads the rest. Several claims are several pins."
    )


def strike(root: Path, n: int, why: str, key: str = KEY) -> tuple[bool, str]:
    """Retire a pin that has simply STOPPED BEING TRUE, without inventing a replacement.

    `--supersedes` already struck a pin, but only by putting another one in its place — it
    answers "this fact changed". It has no answer for "this fact expired", and the first
    stale pin proved it: a spent probe number, true when written, dead an hour later, with
    nothing to replace it. Retiring it through `--supersedes` would have meant writing a
    fact I did not have in order to delete one I did not want, and a store that makes you
    invent an entry to remove an entry will accumulate inventions.

    THE REASON IS REQUIRED, and it is the whole safeguard. Nothing here evicts on a counter
    — there is no counter — because the tool this replaces silently dropped the oldest pin
    and cost a build two hours. A strike is a person deciding, and a decision with a reason
    attached can be read back and argued with.
    The struck text stays on disk under `journal pins --all`, so this hides a fact, never
    erases one.
    """
    why = " ".join((why or "").split())
    if not why:
        return False, "strike what for? say why it stopped being true, in one line"
    noun = "rule" if key == RULES else "pin"
    with state.locked(root):
        items = _all(root, key)
        i = n - 1
        if i < 0 or i >= len(items):
            return False, f"there is no {noun} {n}. `journal {key}` numbers them."
        if items[i].get("struck"):
            return False, f"{noun} {n} is already struck by: {items[i]['struck']}"
        items[i]["struck"] = why
        state.put(root, key, items)
    return True, f"struck {noun} {n}: {items[i]['fact'][:60]}\n  because: {why}"


def promote(root: Path, n: int, at: str, where: dict | None = None) -> tuple[bool, str]:
    """Lift a pin into a rule: the same claim, now for every track.

    THE PIN IS STRUCK, NOT COPIED. Two entries carrying one claim would drift — one gets
    superseded, the other does not — and the far side would be handed both. The strike
    reason names the rule, so `pins --all` still shows where the claim went.
    """
    with state.locked(root):
        items = _all(root)
        i = n - 1
        if i < 0 or i >= len(items):
            return False, f"there is no pin {n}. `journal pins` numbers them."
        if items[i].get("struck"):
            return False, f"pin {n} is already struck by: {items[i]['struck']}"
        rules = _all(root, RULES)
        rules.append({"fact": items[i]["fact"], "at": at, "struck": None,
                      **{k: items[i][k] for k in ("line", "session") if k in items[i]},
                      "promoted_from": n, **(where or {})})
        items[i]["struck"] = f"promoted to rule {len(rules)}"
        state.put(root, RULES, rules)
        state.put(root, KEY, items)
    return True, f"rule {len(rules)}, from pin {n}: {items[i]['fact'][:70]}"


def render(root: Path, *, all_of_them: bool = False, key: str = KEY, width: int = 88) -> str:
    """The list as a person reads it: numbered, wrapped, the provenance on a quiet line.

    The number is what `--full`, `--supersedes`, `strike` and `promote` take, so it is
    always the position in the FULL list — a struck entry keeps its number and is simply
    not shown unless asked for. Renumbering the standing ones would make "pin 3" mean a
    different fact after every strike.
    """
    import textwrap
    items = _all(root, key)
    if not items:
        return "No rules stand." if key == RULES else "Nothing is pinned."
    out = []
    for i, p in enumerate(items, 1):
        struck = p.get("struck")
        if struck and not all_of_them:
            continue
        num = f"{i:>3}  "
        pad = " " * len(num)
        fact = " ".join(p["fact"].split())
        body = textwrap.fill(fact, width=width, initial_indent=num, subsequent_indent=pad)
        meta = []
        when = age(p.get("at", ""))
        if when:
            meta.append(when)
        meta.append(f"line {p['line']}" if p.get("line") else "before lines were kept")
        if p.get("replaced"):
            meta.append(f"replaces {p['replaced']}")
        if p.get("promoted_from"):
            meta.append(f"promoted from pin {p['promoted_from']}")
        if struck:
            body = textwrap.fill(fact, width=width, initial_indent=num + "~~", subsequent_indent=pad)
            body += "~~"
            meta = [f"struck: {struck}"] + meta
        out.append(body + "\n" + textwrap.fill(" · ".join(meta), width=width, initial_indent=pad,
                                                subsequent_indent=pad))
    return "\n\n".join(out)


def around(root: Path, n: int, project: Path, spread: int, key: str = KEY) -> tuple[bool, str]:
    """The conversation around where a pin was written — the reasoning it deliberately omits.

    THIS IS WHY THE PIN CAN BE SHORT. The claim is the pin; the argument is here, in the
    transcript, unedited and in the words both people actually used. Nothing is copied
    between them, so they cannot drift apart.

    A pin from before line numbers were kept SAYS SO instead of guessing at a location. An
    index that points confidently at the wrong page is worse than one that admits a gap.
    """
    import transcript
    noun = "rule" if key == RULES else "pin"
    items = _all(root, key)
    if n < 1 or n > len(items):
        return False, f"there is no {noun} {n}. `journal {key} --all` numbers them."
    p = items[n - 1]
    if not p.get("line"):
        return False, (
            f"{noun} {n} was written before pins recorded where they were said, so there is "
            f"nothing to read around. The fact still stands:\n  {p['fact']}"
        )
    want = p.get("session")
    path = None
    if want:
        cand = transcript.project_dir(project) / want
        path = cand if cand.is_file() else None
    path = path or transcript.newest_session(project)
    if path is None:
        return False, "that pin names a transcript this machine no longer has."
    if want and path.name != want:
        note = f"  ! the session it was written in ({want}) is gone; reading the newest instead\n"
    elif p.get("guessed"):
        note = "  ! the transcript was GUESSED when this pin was written (no session id in the\n" \
               "    environment); the citation may point at another terminal's conversation\n"
    else:
        note = ""
    lines, _ = transcript.read(path)
    here = p["line"]
    # COUNTED IN MESSAGES, NOT IN LINE NUMBERS. A pin is written BY a tool call, so the
    # lines either side of it are that call and its output — measured, the first version
    # printed "nothing was said around that line" for a pin whose conversation was four
    # tool results away. What a reader wants is the nearest MESSAGES, and mostly the ones
    # before: a pin is written after the thing it is about was said.
    spoken = [l for l in lines if l.spoken and (l.text or "").strip()]
    before = [l for l in spoken if l.n <= here][-(spread + 1):]
    after = [l for l in spoken if l.n > here][:max(1, spread // 2)]
    keep = before + after
    edge = before[-1].n if before else here
    body = []
    for l in keep:
        mark = "▸ USER" if l.kind == "human" else "      "
        text = " ".join((l.text or "").split())
        # The pin sits AFTER the last message before it, never on one, so the marker goes
        # on that message rather than pretending a line was the pin itself.
        body.append(f"{'>>' if l.n == edge else '  '}{l.n:>6}  {mark}  {text[:400]}")
    return True, (
        f"# {noun} {n}, written at line {here}\n{note}\n  {p['fact']}\n\n"
        + ("\n".join(body) if body else "  (nothing was said around that line)")
        + f"\n\n  >> marks the line it was pinned at. `journal --back=N` reads further."
    )


def carry(root: Path, source: str = "compact", key: str = KEY) -> str:
    """What a compaction cannot be trusted to keep, handed back AFTER it. Empty if none.

    Not "told to keep" — the summariser is unreachable, so this never shapes the summary.
    It is restored on the far side, at SessionStart, which is the only door that opens.
    The wording says so, because a page claiming a side effect it no longer has is the
    defect this whole system is a reaction to.

    THE HEADER DEPENDS ON WHAT JUST HAPPENED. The journal is shared by every session, so
    the pins are delivered at every start — and a fresh session is holding no summary. A
    header that says "the summary you are holding" to a session that has none is a claim
    about an event that did not happen, in the highest-authority position the system has.
    """
    standing = live(root, key)
    if not standing:
        return ""
    if key == RULES:
        head = "RULES OF THIS PROJECT, on every track. Decided, and still in force:"
    elif source == "compact":
        head = ("FACTS THE SUMMARY YOU ARE HOLDING DID NOT KEEP. They were true before the "
                "compaction and are true now:")
    else:
        head = "FACTS THAT STAND ON THIS TRACK, decided in earlier sessions and still true:"
    return (
        head + "\n"
        + "\n".join(
            f"  - {p['fact']}" + (f"  [{age(p.get('at', ''))}]" if age(p.get("at", "")) else "")
            for p in standing
        )
    )
