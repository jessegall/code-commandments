"""How full the context is — measured from the transcript, never estimated.

Every assistant message records its own `usage`, and input + cache_read + cache_creation
IS the context that message was answering with. So this is a reading, not a guess.

AND THE WARNING HAS TO ARRIVE EARLY. `PreCompact` fires when the decision is already
made — there is no turn left in which to think about what matters, and a pin written then
is written by an agent that is out of room. The moment worth interrupting is while there
is still budget to spend on deciding what must survive it.
"""
from __future__ import annotations

import json
from pathlib import Path

#: The windows that exist, smallest first. The right one is the smallest that fits what
#: this session has ALREADY held.
WINDOWS = (200_000, 1_000_000)
DEFAULT_WINDOW = WINDOWS[0]


def window_for(peak: int, setting: int = 0) -> tuple[int, bool]:
    """(the window, whether that is KNOWN or only the smallest one still possible).

    NOT from the model id. The first version read `[1m]` out of the model name — and the
    message-level model is `claude-opus-5`, with no suffix, in every one of 5,298 records.
    The detector could not fire in the one case it was written for, and reported 241% full
    instead. Ask of any detector: is the thing it looks for present in the case it is FOR?

    AND NOT FROM THE PEAK ALONE, WHILE MORE THAN ONE WINDOW FITS IT. The second version
    took the smallest window the peak fitted and called that the window. In a 1M session
    that reported 54% at 108k tokens — 11% real — and burned every rung of the ladder
    before 20%, after which `warned_at` sat at 0.95 and the ladder was mute for the actual
    compaction. Four wrong nudges and then silence at the moment that mattered.

    So: a `context_window` setting is the answer when it is set. Otherwise the peak is a
    reading only once it has eliminated every window but one, and until then the caller is
    told the window is unknown and must not climb the ladder on it.
    """
    if setting:
        return setting, True
    fits = [w for w in WINDOWS if peak <= w]
    if not fits:
        return WINDOWS[-1], True  # past every known window: it is the largest
    return fits[0], len(fits) == 1


def reading(path: Path) -> tuple[int, int] | None:
    """(tokens in context, peak this transcript has held) from the assistant's own `usage`."""
    used = None
    peak = 0
    if not path.is_file():
        return None
    with path.open() as fh:
        for line in fh:
            try:
                rec = json.loads(line)
            except ValueError:
                continue
            if rec.get("type") != "assistant":
                continue
            msg = rec.get("message") or {}
            usage = msg.get("usage")
            if not usage:
                continue
            used = (
                usage.get("input_tokens", 0)
                + usage.get("cache_read_input_tokens", 0)
                + usage.get("cache_creation_input_tokens", 0)
            )
            peak = max(peak, used)
    if used is None:
        return None
    return used, peak


def pressure(path: Path, setting: int = 0) -> tuple[float, int, int, bool] | None:
    """(share full, tokens, window, window KNOWN). The share is a guess when the last is False."""
    got = reading(path)
    if not got:
        return None
    used, peak = got
    window, known = window_for(peak, setting)
    return used / window, used, window, known


#: What each share IS. A label, not an instruction — every row gets one of these.
_LABELS = {
    "tool_result": "tool output",
    "text": "your own messages",
    "injected": "hook and system blocks",
    "human": "what the user said",
}

#: THE ONLY ROW THE READER CAN ACT ON, and only while it is the one doing the damage.
#:
#: A lever printed beside a share nobody can move is the defect this system keeps finding:
#: a line that fires when it does not apply teaches the reader to skip the block, and then
#: the line that DID apply gets skipped with it. Eleven wrong nudges to catch three was the
#: measurement that first made the point. Nothing can be done about how much the user said,
#: and "write less" is not advice worth interrupting for — so those rows are shown as facts
#: and left alone.
#:
#: The threshold means the suggestion appears when tool output is genuinely the reason the
#: context is full, not merely present in it. Below it, the number still prints and says
#: nothing.
_LEVER_AT = 0.40
_LEVER = {
    "tool_result": "read narrower next time: grep or sed a range, not whole files",
}


def shape(lines) -> list[tuple[str, float]]:
    """What is actually filling the context, by share of characters, biggest first.

    A FACT, NOT AN INSTRUCTION. The temptation at 75% is to append advice — "read files in
    ranges, prefer grep" — and advice is the one thing this system has a rule against: it
    cannot be measured, nothing fires when it is ignored, and it fails silently. That is
    the shape of the secretary, which was deleted for never once being used.

    A share is different. "tool output is 47% of this context" is checkable, specific to
    the session in front of you, and wrong tomorrow if the session changes — which is
    exactly what advice can never be. The lever is named beside the number rather than
    preached on its own.

    Characters, not tokens: the transcript does not record per-message token counts, and a
    ratio of characters is close enough to a ratio of tokens to point at the right half of
    the context. It is a proportion, never a total — the total comes from `reading`, which
    is measured.
    """
    total = 0
    by_kind: dict[str, int] = {}
    for l in lines:
        n = len(l.text or "")
        if not n:
            continue
        by_kind[l.kind] = by_kind.get(l.kind, 0) + n
        total += n
    if not total:
        return []
    return sorted(((k, v / total) for k, v in by_kind.items()), key=lambda x: -x[1])


#: What each rung is FOR. The ladder exists because one warning at 75% is both too late to
#: think and too early to be urgent; four rungs let the message change with the situation
#: instead of repeating itself louder.
_RUNGS = {
    0.50: "Half the window is gone. This is the cheapest moment to think about what has to "
          "outlive it — you have room to be wrong and fix it.",
    0.70: "A compaction will probably happen before this session ends.",
    0.90: "A compaction is close. This is the last comfortable moment to write one.",
    0.95: "A compaction is imminent. This is the last warning you get.",
}

#: THE ANTI-PADDING LINE, and it is the whole reason this nudge is safe to repeat.
#:
#: A message that says "pin something" four times a session will be obeyed four times, and
#: a store of obedient pins is worse than an empty one: every pin is re-read in full after
#: every compaction for the rest of the project, so a weak one is a tax the writer pays
#: once and every future reader pays forever. The nudge therefore states the COST and
#: blesses the empty answer explicitly, rather than asking for a contribution.
_NOTHING_IS_FINE = (
    "Most stretches produce none, and none is the right answer here more often than not. "
    "A pin that did not need to be there is not free — it is re-read in full after every "
    "compaction from now on, and it dilutes the ones that matter."
)


def warning(used: int, window: int, pinned: int, made_of=(), rung: float = 0.0,
            latest: str = "", since: int = 0, gated: bool = False) -> str:
    """The context nudge for one rung of the ladder.

    IT REPORTS, THEN ASKS, AND NEVER DEMANDS. Everything above the request is a measured
    fact — how full, what filled it, how many pins stand, what the last one said — because
    a nudge built on facts can be judged by the reader, while one built on urgency can only
    be obeyed or ignored. Obedience is the failure mode here: pins are re-read forever, so a
    padded store costs every future reader something the writer never sees.
    """
    pct = 100 * used / window
    said = _RUNGS.get(rung, "A compaction is coming.")
    out = [
        f"CONTEXT IS {pct:.0f}% FULL — {used:,} of {window:,}. {said}",
        "A compaction keeps what was DONE and drops what was DECIDED. Pins and open work "
        "are what cross it; everything else has to be read back on purpose.",
    ]
    standing = f"{pinned} pin(s) stand" + (f", {since} written since the last warning" if since else "")
    out.append(
        f"{standing}. A pin is a CLAIM a later reader would get WRONG without — a ruling, a "
        f"constraint, a decision and why. Never a status, a count, or what you just did; "
        f"those rot into confident falsehoods wearing the same authority as the facts that "
        f"still hold."
    )
    if latest:
        # THE STANDARD, SHOWN RATHER THAN DESCRIBED. "Short and concrete" is an instruction
        # nobody can check themselves against; the last pin that was accepted is one they can.
        out.append(f"The last one written, for the shape of it:\n  {latest}")
    out.append('  .journal/journal.py remember "<the claim, in one line>"')
    out.append(_NOTHING_IS_FINE)
    if gated:
        # A DECISION IS REQUIRED, A PIN IS NOT. The gate is what makes this land — the
        # nudge alone was measured and did not — and "nothing" has to be as cheap a way
        # through it as a pin, or the gate manufactures pins.
        out.append(
            "NOTHING ELSE RUNS UNTIL YOU HAVE DECIDED. The next tool call is denied until "
            "one of these has run:\n"
            '  .journal/journal.py remember "<the claim>"\n'
            '  .journal/journal.py nothing "<why nothing here needs pinning>"\n'
            "Both are one command. The journal's own commands still run, so `search` and "
            "`--back=1` are available to decide with."
        )
    if made_of:
        out.append(
            "WHAT IS ACTUALLY IN HERE, by share of what was said:\n"
            + "\n".join(
                f"  {share:>5.0%}  {_LABELS.get(kind, kind)}"
                + (f"\n         {_LEVER[kind]}" if kind in _LEVER and share >= _LEVER_AT else "")
                for kind, share in made_of[:4]
            )
        )
    return "\n\n".join(out)
