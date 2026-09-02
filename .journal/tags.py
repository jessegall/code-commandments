"""What a message is FOR, written as a bracketed prefix opening a line.

A TAG DESCRIBES A MESSAGE. It does not commit you to anything, which is why it is free and
why you should use one every time. Starting a piece of WORK is a different act — it is a
commitment, it must be done with thought, and it therefore costs a command. See `work.py`.

THE TAG RIDES ON A MESSAGE YOU WERE SENDING ANYWAY, which is the whole reason this works.
Nothing has to be remembered, no command has to be run, and there is no store to keep
current — the filing is a side effect of speaking. A mechanism that must be CALLED to
prevent a loss is a discipline; this one is not.

The words are SPELLED OUT because the user reads them. A tag is part of what the agent
says, in the terminal, where `[!discovery]` is a word a human reads and `[d]` is a code
they must learn.

A tag's spelling is a STORAGE FORMAT — it is what a past transcript already carries — so
renaming one makes every message already filed under it read back as untagged.
"""
from __future__ import annotations

import re
from dataclasses import dataclass

# THE TAG OPENS THE MESSAGE. Not a line — the message, before anything else but whitespace.
#
# An earlier version matched the start of ANY line, so that an agent which answered first
# and declared below had still declared. That is friendlier and it is wrong: the moment a
# message EXPLAINS the vocabulary — a nudge quoted back, a page documenting the tags, this
# very comment — every listed tag matches and the message files itself as something it was
# only ever talking about. A record that cannot tell using a word from mentioning one will
# fill with entries nobody wrote.
#
# So: talking about a tag is not using one, and the only way to use one is to lead with it.
PATTERN = re.compile(r"\A\s*\[!([a-z]+)\]")


@dataclass(frozen=True)
class Tag:
    name: str
    line: str  # what it is for, shown by `journal instructions`


TAGS = {
    t.name: t
    for t in (
        Tag("discovery", "the real shape of something you did not know"),
        Tag("correction", "something you had wrong is now right"),
        Tag("blocked", "blocked, and on what"),
        # WHAT IS LEFT AFTER `update` WAS TAKEN OUT. Every remaining tag describes the
        # message it rides on and nothing else, so none of them can be worn wrongly:
        #   info   is about the WORLD        — a monitor started, a long build running
        #   reply  is about the CONVERSATION — you answering what was asked
        # Progress on the work is no longer a tag at all. See LEGACY below.
        Tag("info", "something happening that is worth knowing but is not work progress"),
        # THE TAG THAT MAKES THE RULE ENFORCEABLE. Every message carries one, so the check
        # is binary and needs no judgement — and a message that carries nothing durable must
        # therefore have something honest to wear. Without this, "tag every message" is a
        # rule the agent MUST break, and a rule that must be broken teaches that rules can
        # be. It files as routine and the digest drops it.
        Tag("reply", "answering what was asked, directly. Routine; kept out of the digest"),
    )
}


#: RETIRED, AND STILL READ. `update` was a tag until the user struck it: it was the one
#: whose correctness depended on something OUTSIDE the message it rode on — an open scope
#: — so it was the only one that could be worn wrongly, and the first thing it did in the
#: wild was answer a direct question. Progress on work is now `journal update`, a command,
#: because it is about the work rather than about the message.
#:
#: It stays readable because A TAG'S SPELLING IS A STORAGE FORMAT: transcripts already on
#: disk carry `[!update]`, and deleting the word outright would make every one of those
#: messages read back as untagged — a silent rewriting of what was already filed.
LEGACY = {"update": "retired — progress on work is `journal update` now"}


def found(text: str) -> list[str]:
    """Every tag opening a line of this message, in order, known ones only.

    At most one, because a message is one thing. An unknown bracket is NOT silently
    treated as a tag: a typo would otherwise file a message under a name nothing reads
    back, which is the shape of every write that reports success and lands nowhere.
    """
    m = PATTERN.match(text or "")
    return [m.group(1)] if m and m.group(1) in (TAGS.keys() | LEGACY.keys()) else []


#: Tags that mean "nothing durable here". Filed, and elided from a read-back.
ROUTINE = {"reply"}


def carried(text: str) -> bool:
    """Did this message say something worth reading back?

    A `[!reply]` is TAGGED — the message obeyed the rule — and carries nothing, so it is
    counted in an elision rather than shown.
    """
    return any(t not in ROUTINE for t in found(text))


def strip(text: str) -> str:
    """The message without its leading tag, for rendering a digest line."""
    return PATTERN.sub("", text or "", count=1).strip()
