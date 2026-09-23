namespace Shop.Pricing;

// The discount a loyalty member gets at the till.
public static class LoyaltyTiers
{
    public static int Percent(bool member, int visits)
    {
        // @sin NestedTernary
        return member ? (visits > 20 ? 10 : 5) : 0;
    }

    // @righteous NestedTernary
    public static int Welcome(bool member) => member ? 5 : 0;
}
