"""What is worth reading back, out of everything that was said.

A digest keeps the user's own words UNTOUCHED and enough either side of them to know what
they were answering. A bare "yes please" is meaningless without the thing it agreed to,
which is why a prompt is never shown alone.

Everything else is kept only when the agent said it CARRIED something. A `[!reply]` obeyed
the rule and carries nothing, so it is counted in an elision rather than shown — the same
as an untagged one. A quiet stretch reads back as `⋯ 41 messages ⋯`, which is its worth.
"""
from __future__ import annotations

import tags
import transcript

CONTEXT = 2  # messages either side of a prompt that are its context


def _near_prompt(lines, i: int, prompts: set[int]) -> bool:
    """Does a prompt sit close enough behind or ahead for this line to be its context?"""
    return any(abs(i - p) <= CONTEXT for p in prompts)


def select(lines) -> list:
    """The lines a reader needs, in order.

    The user's words ALWAYS; the agent's when they carried a tag, or when they sit close
    enough to a prompt to be what it answered or what answered it.

    A message with no TEXT is not context: a turn that only called a tool said nothing, and
    keeping it spends a line of the digest to show a blank.
    """
    # ONLY A FILING UNIT CAN BE KEPT FOR PROXIMITY. Nearness to a prompt is a weak reason
    # — it keeps a line for where it sat, not for what it said — and applied to every text
    # block it dragged in the scaffolding: "Now wiring it into the CLI", "Let me check the
    # other file". The hook already stopped treating those as messages. This is the same
    # rule, read from the same place, so the two halves of the system cannot drift again.
    #
    # A TAG STILL WINS ON ITS OWN. A line that declared what it carried is kept whether or
    # not it ended its turn: the agent said it was worth reading back, and the digest has
    # no business overruling that with a structural guess.
    units = transcript.filing_units(lines)
    spoken = [l for l in lines if l.spoken and (l.text or "").strip()]
    prompts = {i for i, l in enumerate(spoken) if l.kind == "human"}
    # THE MESSAGE THE USER REACTED TO, AND THE ONE THAT ANSWERED THEM. A prompt is nearly
    # always a reaction: a correction, a steer, a "yes, do that". Shown alone it is a reply
    # to nothing. Proximity mostly keeps these, but a hook hold between the answer and the
    # prompt pushes the answer out of range — so the last filed message before each prompt
    # and the first after it are kept by name, whatever the distance.
    around: set[int] = set()
    unit_idx = [i for i, l in enumerate(spoken) if l.n in units]
    for p in prompts:
        before = [i for i in unit_idx if i < p]
        after = [i for i in unit_idx if i > p]
        if before:
            around.add(before[-1])
        if after:
            around.add(after[0])
    keep = []
    for i, line in enumerate(spoken):
        if (
            line.kind == "human"
            or tags.carried(line.text)
            or i in around
            or (line.n in units and _near_prompt(spoken, i, prompts))
        ):
            keep.append(line)
    return keep


def render(lines, *, elide: bool = True) -> str:
    """The digest as it reads: the user in their own words, the agent indented behind them.

    A dropped stretch is COUNTED rather than hidden. A reader who cannot see that forty
    messages were skipped cannot tell a quiet stretch from a missing one.
    """
    keep = select(lines)
    kept = {l.n for l in keep}
    out: list[str] = []
    skipped = 0
    for line in (l for l in lines if l.spoken and (l.text or "").strip()):
        if line.n not in kept:
            skipped += 1
            continue
        if skipped and elide:
            out.append(f"      ⋯ {skipped} message(s) ⋯")
        skipped = 0
        out.append(_one(line))
    if skipped and elide:
        out.append(f"      ⋯ {skipped} message(s) ⋯")
    return "\n".join(out)


def _one(line) -> str:
    found = tags.found(line.text)
    body = tags.strip(line.text) if found else (line.text or "").strip()
    body = " ".join(body.split())
    if line.kind == "human":
        return f"\n{line.n:>5}  ▸ USER: {body}"
    mark = f"[{'/'.join('!' + t for t in found)}] " if found else ""
    return f"{line.n:>5}    {mark}{body[:600]}"


def users_only(lines) -> str:
    """Only the user's own words, in full, never trimmed.

    The one tier that is never summarised: what somebody TOLD the agent is the half a
    compaction is most likely to drop and the half nothing else can re-derive.
    """
    out = []
    for line in lines:
        if line.kind == "human":
            out.append(f"\n{line.n:>5}  {line.ts[:19]}\n{(line.text or '').strip()}")
    return "\n".join(out)
