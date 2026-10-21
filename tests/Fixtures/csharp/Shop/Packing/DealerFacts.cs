namespace Shop.Packing;

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

    // @sin FilledParameter
    // @sin FeatureEnvy
    private void ApplyPlan(DealerRecord record)
    {
        record.Plan = plans.TryGetValue(record.DealerId, out var plan) ? plan : "free";
    }

    // @sin FilledParameter
    // @sin FeatureEnvy
    private void ApplyLanguage(DealerRecord record)
    {
        record.Language = languages.TryGetValue(record.DealerId, out var language) ? language : "nl";
    }
}

// @fixed FilledParameter
public sealed class DealerFactsReader(IReadOnlyDictionary<string, string> plans, IReadOnlyDictionary<string, string> languages)
{
    public string Gather(string dealerId)
    {
        return $"{dealerId}:{this.PlanOf(dealerId)}:{this.LanguageOf(dealerId)}";
    }

    private string PlanOf(string dealerId) => plans.TryGetValue(dealerId, out var plan) ? plan : "free";

    private string LanguageOf(string dealerId) => languages.TryGetValue(dealerId, out var language) ? language : "nl";
}

public sealed class DealerRecord(string dealerId)
{
    public string DealerId { get; } = dealerId;

    public string Plan { get; set; } = "";

    public string Language { get; set; } = "";
}
