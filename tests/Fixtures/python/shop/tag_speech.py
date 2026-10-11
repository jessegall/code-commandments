# @example SideTableTrait bad
# Which tags keep their words out of the chat is kept as a set of the ones it is true of, beside the
# tags themselves. A tag added tomorrow is silently spoken aloud, and has nowhere to say otherwise.
from dataclasses import dataclass


@dataclass(frozen=True)
class Tag:
    name: str
    runs: str
    hint: str


TAGS = (
    Tag("reply", "message reply", "answer the message"),
    Tag("log", "work log", "write what moved"),
    Tag("await", "work await", "say what is waited on"),
    Tag("todo", "todo create", "file it"),
)

# @sin SideTableTrait
QUIET = {"reply", "log", "await"}


def said_in_chat(name: str) -> bool:
    return name not in QUIET
