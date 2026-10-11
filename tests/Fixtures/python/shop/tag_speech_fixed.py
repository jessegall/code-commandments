# @example SideTableTrait good
# The repair: the trait is a field each tag answers for itself, so a tag written tomorrow has to say
# whether it is spoken aloud, and the set that reads it is derived from the tags rather than kept beside
# them. There is no second place left to forget.
from dataclasses import dataclass


# @fixed SideTableTrait
@dataclass(frozen=True)
class SpokenTag:
    name: str
    runs: str
    hint: str
    said_in_chat: bool = True


# @fixed SideTableTrait
SPOKEN_TAGS = (
    SpokenTag("reply", "message reply", "answer the message", said_in_chat=False),
    SpokenTag("log", "work log", "write what moved", said_in_chat=False),
    SpokenTag("await", "work await", "say what is waited on", said_in_chat=False),
    SpokenTag("todo", "todo create", "file it"),
)


# @fixed SideTableTrait
def said_aloud(tag: SpokenTag) -> bool:
    return tag.said_in_chat
