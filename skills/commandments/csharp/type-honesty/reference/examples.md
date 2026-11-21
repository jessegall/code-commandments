# C# type honesty — a type must say what is true — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### csharp-filled-parameter

a method returning nothing that writes a property of an object it was handed, whose caller then reads what it handed over — the signature says the object goes in and nothing says it comes out changed

```cs
----------[ Bad ]----------

// The facts a dealer's record is built from, gathered by steps that each write one field onto the record they
// were handed. The gatherer reads every field straight back off, so each step's signature says the record goes
// in and says nothing at all about what comes out of it. Below them, the repair: each step answers with its
// field and the gatherer puts it where it belongs.
public sealed class DealerFactsGatherer(IReadOnlyDictionary<string, string> plans, IReadOnlyDictionary<string, string> languages)
{
    public string Gather(string dealerId)
    {
        var record = new DealerRecord(dealerId);
        this.ApplyPlan(record);
        this.ApplyLanguage(record);

        return $"{record.DealerId}:{record.Plan}:{record.Language}";
    }

    private void ApplyPlan(DealerRecord record)
    {
        record.Plan = plans.TryGetValue(record.DealerId, out var plan) ? plan : "free";
    }

    private void ApplyLanguage(DealerRecord record)
    {
        record.Language = languages.TryGetValue(record.DealerId, out var language) ? language : "nl";
    }
}

----------[ Good ]----------

public sealed class DealerFactsReader(IReadOnlyDictionary<string, string> plans, IReadOnlyDictionary<string, string> languages)
{
    public string Gather(string dealerId)
    {
        return $"{dealerId}:{this.PlanOf(dealerId)}:{this.LanguageOf(dealerId)}";
    }

    private string PlanOf(string dealerId) => plans.TryGetValue(dealerId, out var plan) ? plan : "free";

    private string LanguageOf(string dealerId) => languages.TryGetValue(dealerId, out var language) ? language : "nl";
}
```

### csharp-impossible-guard

`if (order == null)` where `order` is typed `Order` and not `Order?` — one of the two is wrong, and both are worth knowing

```cs
----------[ Bad ]----------

// Translations of one string, asked for by language. The first method answers false when the language is null,
// which its own type says cannot happen: either the type is wrong and callers do pass null, or nobody needed
// the check and every reader has to work that out. Below it the repair, and the two look-alikes that are not
// the sin: a guard that asserts the contract and dies, and one on a parameter typed as it really is.
public sealed class LanguageTexts(IReadOnlyDictionary<string, string> texts)
{
    public bool HasTranslation(Language language)
    {
        if (language == null)
        {
            return false;
        }

        return texts.ContainsKey(language.Code);
    }

    public bool HasTextIn(Language language) => texts.ContainsKey(language.Code);

    public string TextIn(Language language)
    {
        if (language == null)
        {
            throw new ArgumentNullException(nameof(language));
        }

        return texts[language.Code];
    }

    public string TextOrPrimary(Language? language, string primary)
    {
        if (language == null)
        {
            return primary;
        }

        return texts.TryGetValue(language.Code, out var text) ? text : primary;
    }
}

----------[ Good ]----------

// Translations of one string, asked for by language. The first method answers false when the language is null,
// which its own type says cannot happen: either the type is wrong and callers do pass null, or nobody needed
// the check and every reader has to work that out. Below it the repair, and the two look-alikes that are not
// the sin: a guard that asserts the contract and dies, and one on a parameter typed as it really is.
public sealed class LanguageTexts(IReadOnlyDictionary<string, string> texts)
{
    public bool HasTranslation(Language language)
    {
        if (language == null)
        {
            return false;
        }

        return texts.ContainsKey(language.Code);
    }

    public bool HasTextIn(Language language) => texts.ContainsKey(language.Code);

    public string TextIn(Language language)
    {
        if (language == null)
        {
            throw new ArgumentNullException(nameof(language));
        }

        return texts[language.Code];
    }

    public string TextOrPrimary(Language? language, string primary)
    {
        if (language == null)
        {
            return primary;
        }

        return texts.TryGetValue(language.Code, out var text) ? text : primary;
    }
}
```
