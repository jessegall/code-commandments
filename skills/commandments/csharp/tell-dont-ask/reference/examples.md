# C# tell, don't ask — behaviour lives with its data — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### csharp-feature-envy

a method that loops another object's collection or writes its members, reaching into it more than into its own state — behaviour exiled from the object it works on

```cs
----------[ Bad ]----------

public int Weigh(Tote tote)
{
    var grams = 0;

    foreach (var item in tote.Items)
    {
        grams += item.Grams;
    }

    return grams;
}

----------[ Good ]----------

public int TotalGrams() => Items.Sum(item => item.Grams);
```

### csharp-side-table-trait

a trait of a type's registered instances kept as a separate collection of their names, rather than as something each instance answers for itself

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
using System.Linq;

namespace Shop.Tags;

public sealed record SpokenTag(string Name, string Command, bool SaidInChat);

public static class TagSpeechFixed
{
    public static readonly SpokenTag[] All = {
        new SpokenTag("reply", "message reply", false),
        new SpokenTag("log", "work log", false),
        new SpokenTag("todo", "todo create", true)
    };

    private static readonly HashSet<string> Quiet = All.Where(tag => !tag.SaidInChat)
        .Select(tag => tag.Name).ToHashSet();

    public static bool SaidInChat(SpokenTag tag) => tag.SaidInChat;
}
```

### csharp-type-switch

`shape switch { Circle c => …, Square s => … }` — asking which of your own types a value is, to decide what to do with it

```cs
----------[ Bad ]----------

public static CardOwner Of(IHasOwner source) => source switch
{
    OwnedCard card => card.Owner,
    OwnedVoucher voucher => voucher.Owner,
    _ => throw new ArgumentOutOfRangeException(nameof(source)),
};

----------[ Good ]----------

public static CardOwner Asked(IHasOwner source) => source.Owner;
```
