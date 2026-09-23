namespace Shop.Pricing;

// A partner file names each customer's tier as text, and the import maps every spelling by hand onto the
// member of Tier with that same name — Enum.Parse, rewritten one arm at a time.
public static class TierImport
{
    public static Tier? Read(string raw) =>
        // @sin StringMirrorsEnum
        raw.ToLowerInvariant() switch
        {
            "bronze" => Tier.Bronze,
            "silver" => Tier.Silver,
            "gold" => Tier.Gold,
            "platinum" => Tier.Platinum,
            _ => null,
        };
}
