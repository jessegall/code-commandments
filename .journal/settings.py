"""`.journal/settings.json` — what a project may tune, and nothing it may not.

TWO RULES, both paid for by the tool this replaces:

An UNKNOWN KEY IS REPORTED, never ignored. A setting quietly doing nothing is
indistinguishable from a setting being obeyed, and that shape cost the last system
seventeen hours of hooks that were wired, listed as registered, and never fired.

A MISSING FILE IS FINE. Defaults are the whole configuration until somebody disagrees
with one, so the file is a record of DISAGREEMENTS rather than a wall of restated
defaults nobody reads.
"""
from __future__ import annotations

import json
from pathlib import Path

DEFAULTS = {
    # How much either side of a prompt is kept, so "yes please" has its question.
    "context_messages": 2,

    # `nudge_untagged` LIVED HERE and is gone: it was read only by a MessageDisplay handler
    # that wrote a key nothing read. A setting that does nothing is the failure this module
    # exists to report, so anyone who kept it is told so by the unknown-key line.

    # THE ONE RULE: a message that carries no tag is a message that filed nothing, and it
    # is held for at the STOP, never mid-task. A tool count is an arbitrary boundary
    # that can fire mid-thought; a stop is the moment the stretch is about to be lost, which
    # is the moment worth holding. Set false to nudge and never hold.
    "hold_stop_on_untagged": True,

    # A write is refused while no work is open. It was declared here for weeks and never
    # built; it is built now because the nudge was measured and found not to land — a real
    # session tagged 843 lines faithfully and ran `journal start` zero times. ON by default,
    # because a gate nobody turns on is the same as the setting that was never implemented.
    "gate_writes_on_start": True,

    # A tool result bigger than this, and bigger than anything before it this session, is
    # reported once. Characters, not tokens: it is the transcript's own unit and roughly
    # four to one. 0 turns it off.
    "tool_cost_floor": 20_000,

    # THE CAP IS ON LENGTH, NOT COUNT. Measured: a hundred pins is about 4,700 tokens,
    # under half a percent of a million-token window — so counting them rationed something
    # that costs nothing, while the real damage was a pin grown into a paragraph and
    # re-read in full at every compaction forever. A pin is a CLAIM; its reasoning stays in
    # the transcript, and `journal pins <n> --full` reads the stretch around it. 0 removes
    # the limit. It was 140, then 300, and a claim with its one constraint kept brushing
    # it; 400 is about 100 tokens re-read per start and still short of a paragraph. What
    # does not fit in 400 is several claims, and several claims are several pins.
    "pin_max_chars": 400,

    # Messages either side of a pin that `journal pins <n> --full` shows.
    "pin_context": 4,

    # THE RUNGS AT WHICH THE CONTEXT NUDGE FIRES, each one once. A single warning could not
    # be both early enough to think in and late enough to feel urgent, so it is a ladder:
    # 50% is the cheap moment to decide what must outlive the window, 95% is the last word.
    # Empty disables it. See `context._RUNGS` for what each rung says.
    "context_warn_ladder": [0.5, 0.7, 0.9, 0.95],

    # AFTER A RUNG, NOTHING RUNS UNTIL A DECISION IS MADE. The rung's hold was measured
    # and did not land: the user had to remind the agent to pin. So the next tool call is
    # denied until `remember` or `nothing "<why>"` has been run — a decision, not a pin,
    # because a gate that manufactures pins is the padding the ladder warns against.
    "gate_after_context_rung": True,

    # WHERE A SUBAGENT IS HANDED THE RULES AGAIN, as a share of ITS OWN window. Subagents
    # get no SessionStart and write no journal, but a rule binds their work as much as the
    # main agent's, so the rules ride their first tool call and come back at these marks:
    # in a long context the block from the start is far behind and attention fades.
    # Delivered as context, never a hold — a subagent has nothing to decide about pins.
    "subagent_rules_ladder": [0.25, 0.5, 0.75],

    # THE WINDOW THE LADDER IS CLIMBED AGAINST. 0 means unknown, and unknown means the
    # ladder stays SILENT until the session's own peak has ruled out every window but one.
    # It is not inferred from the smallest window that fits: that reported 54% at 108k
    # tokens of a 1M window and burned every rung before 20%, after which the ladder was
    # mute for the real compaction. `journal verify` says when this is unset.
    "context_window": 0,


    # Reminders to silence, by name, e.g. ["quiet"].
    "silenced": [],
}

PATH = "settings.json"


def load(root: Path) -> tuple[dict, list[str]]:
    """Settings, and every complaint about the file. Never raises.

    A broken settings file must not stop the journal: the record is what a session falls
    back on when everything else is gone, so it degrades to defaults and SAYS SO.
    """
    out = dict(DEFAULTS)
    problems: list[str] = []
    f = root / PATH
    if not f.is_file():
        return out, problems
    try:
        data = json.loads(f.read_text())
    except ValueError as e:
        return out, [f"{PATH}: not valid JSON ({e}) — every default is in force"]
    if not isinstance(data, dict):
        return out, [f"{PATH}: expected an object — every default is in force"]
    for key, value in data.items():
        # JSON has no comments and everybody writes them anyway. A `//` key is a note to
        # the next reader, not a setting, so it is neither applied nor complained about.
        if key.startswith("//"):
            continue
        if key not in DEFAULTS:
            problems.append(f"{PATH}: unknown setting {key!r} — it does nothing")
            continue
        want = type(DEFAULTS[key])
        if want is bool and not isinstance(value, bool):
            problems.append(f"{PATH}: {key} wants true/false, got {value!r} — default kept")
            continue
        if want is float and not isinstance(value, (int, float)):
            problems.append(f"{PATH}: {key} wants a number, got {value!r} — default kept")
            continue
        if want is int and not isinstance(value, int):
            problems.append(f"{PATH}: {key} wants a number, got {value!r} — default kept")
            continue
        if want is list and not isinstance(value, list):
            problems.append(f"{PATH}: {key} wants a list, got {value!r} — default kept")
            continue
        out[key] = value
    return out, problems
