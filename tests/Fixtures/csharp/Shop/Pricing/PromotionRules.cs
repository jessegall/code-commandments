namespace Shop.Pricing;

// How a promotion is shown in the shop.
public sealed record PromotionRule(string Code, decimal PercentOff)
{
    // @sin BareStatePredicate
    public bool Publishes { get; init; }
}
