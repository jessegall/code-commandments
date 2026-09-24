namespace Shop.Reports;

using Shop.Pricing;

// Counts which kinds of promotion were used this week.
public sealed class PromotionReport
{
    private int percent;

    private int amount;

    public void Count(IEnumerable<Promotion> used)
    {
        foreach (var promotion in used)
        {
            // @sin TypeSwitch
            switch (promotion)
            {
                case PercentOff:
                    percent++;
                    break;
                case AmountOff:
                    amount++;
                    break;
            }
        }
    }

    public string Summary() => $"{percent} percent, {amount} amount";
}
