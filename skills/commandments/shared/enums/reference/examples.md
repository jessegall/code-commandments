# Enums: give a closed set one source — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### side-table-trait — in PHP

A separate list repeats which cases have a trait

```php
----------[ Bad ]----------

// in app/Shipping/CarrierService.php
namespace Shop\Shipping;

/**
 * Which services need a signature is kept as a list of the cases it is true of, beside the cases
 * themselves. A service added tomorrow is silently not one of them, and has nowhere to say it is.
 */
enum CarrierService: string
{
    case Standard = 'standard';
    case Express = 'express';
    case Insured = 'insured';
    case Pallet = 'pallet';

    private const SIGNED_FOR = [self::Insured, self::Pallet];

    public function needsSignature(): bool
    {
        return in_array($this, self::SIGNED_FOR, true);
    }
}

----------[ Good ]----------

// in app/Shipping/ReturnWindow.php
namespace Shop\Shipping;

/**
 * The same trait, answered by the case instead of listed beside it: a window added tomorrow cannot
 * be written without saying whether it is extended, because the match has no case to fall through to.
 */
enum ReturnWindow: string
{
    case Fortnight = 'fortnight';
    case Month = 'month';
    case Quarter = 'quarter';

    public function isExtended(): bool
    {
        return match ($this) {
            self::Fortnight => false,
            self::Month, self::Quarter => true,
        };
    }
}
```

### side-table-trait — in TypeScript

A separate list repeats which cases have a trait

```ts
----------[ Bad ]----------

// in client/tag-speech.ts
type SpeechTag = { name: string; command: string }

const TAGS: SpeechTag[] = [
  { name: 'reply', command: 'message reply' },
  { name: 'log', command: 'work log' },
  { name: 'todo', command: 'todo create' },
]

const QUIET = new Set(['reply', 'log'])

export function saidInChat(name: string): boolean {
  return !QUIET.has(name)
}

----------[ Good ]----------

// in client/tag-speech-fixed.ts
type SpokenTag = { name: string; command: string; saidInChat: boolean }

const TAGS: SpokenTag[] = [
  { name: 'reply', command: 'message reply', saidInChat: false },
  { name: 'log', command: 'work log', saidInChat: false },
  { name: 'todo', command: 'todo create', saidInChat: true },
]

const QUIET = new Set(TAGS.filter((tag) => !tag.saidInChat).map((tag) => tag.name))

export function saidInChat(tag: SpokenTag): boolean {
  return tag.saidInChat
}
```

### side-table-trait — in Python

A separate list repeats which cases have a trait

```py
----------[ Bad ]----------

# in shop/tag_speech.py
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

QUIET = {"reply", "log", "await"}

def said_in_chat(name: str) -> bool:
    return name not in QUIET

----------[ Good ]----------

# in shop/tag_speech_fixed.py
# The repair: the trait is a field each tag answers for itself, so a tag written tomorrow has to say
# whether it is spoken aloud, and the set that reads it is derived from the tags rather than kept beside
# them. There is no second place left to forget.
from dataclasses import dataclass

@dataclass(frozen=True)
class SpokenTag:
    name: str
    runs: str
    hint: str
    said_in_chat: bool = True

SPOKEN_TAGS = (
    SpokenTag("reply", "message reply", "answer the message", said_in_chat=False),
    SpokenTag("log", "work log", "write what moved", said_in_chat=False),
    SpokenTag("await", "work await", "say what is waited on", said_in_chat=False),
    SpokenTag("todo", "todo create", "file it"),
)

def said_aloud(tag: SpokenTag) -> bool:
    return tag.said_in_chat
```

### side-table-trait — in C#

A separate list repeats which cases have a trait

```cs
----------[ Bad ]----------

// in Shop/Tags/TagSpeech.cs
using System.Collections.Generic;

namespace Shop.Tags;

public sealed record SpeechTag(string Name, string Command);

public static class TagSpeech
{
    public static readonly SpeechTag[] All = {
        new SpeechTag("reply", "message reply"),
        new SpeechTag("log", "work log"),
        new SpeechTag("todo", "todo create")
    };

    private static readonly HashSet<string> Quiet = new() { "reply", "log" };

    public static bool SaidInChat(string name) => !Quiet.Contains(name);
}

----------[ Good ]----------

// in Shop/Tags/TagSpeechFixed.cs
using System.Collections.Generic;

namespace Shop.Tags;

public sealed record SpokenTag(string Name, string Command, bool SaidInChat);

public static class TagSpeechFixed
{
    public static readonly SpokenTag[] All = {
        new SpokenTag("reply", "message reply", false),
        new SpokenTag("log", "work log", false),
        new SpokenTag("todo", "todo create", true)
    };

    public static bool SaidInChat(SpokenTag tag) => tag.SaidInChat;
}
```
