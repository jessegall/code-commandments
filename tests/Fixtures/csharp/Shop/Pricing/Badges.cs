namespace Shop.Pricing;

public enum Tier { Bronze, Silver, Gold, Platinum, Staff, Partner }

// Two tables that answer a tier with a constant — a label and a colour. They share a shape because a
// table always does; each is data, not a procedure written twice.
public static class Badges
{
    // @righteous NearDuplicateMethod
    public static string Label(Tier tier) => tier switch
    {
        Tier.Bronze => "Bronze member",
        Tier.Silver => "Silver member",
        Tier.Gold => "Gold member",
        Tier.Platinum => "Platinum member",
        Tier.Staff => "Staff",
        Tier.Partner => "Partner",
        _ => "Guest",
    };

    // @righteous NearDuplicateMethod
    public static string Colour(Tier tier) => tier switch
    {
        Tier.Bronze => "#cd7f32",
        Tier.Silver => "#c0c0c0",
        Tier.Gold => "#ffd700",
        Tier.Platinum => "#e5e4e2",
        Tier.Staff => "#2e7d32",
        Tier.Partner => "#1565c0",
        _ => "#9e9e9e",
    };
}
