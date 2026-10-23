namespace Shop.Pricing;

public sealed record PriceRule(string Function, IReadOnlyList<PriceRule> Parts, decimal? Amount);

// A walker over the price rules a feed stored. The rules are plain data read back from storage, and evaluating
// one needs the basket it is evaluated against and the walk itself, so a rule that evaluated itself would have to
// be handed the walker's whole world.
public sealed class RuleWalker
{
    private readonly decimal basket;

    public RuleWalker(decimal basket)
    {
        this.basket = basket;
    }

    // @righteous FeatureEnvy
    public decimal Evaluate(PriceRule rule)
    {
        var total = rule.Amount ?? 0m;

        foreach (var part in rule.Parts)
        {
            total += Evaluate(part);
        }

        return rule.Function == "share" ? total * basket : total;
    }
}
