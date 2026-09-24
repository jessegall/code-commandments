namespace Shop.Reports;

public static class ReportRounding
{
    /* Half-up on purpose, not banker's rounding: that is not an oversight. */
    // @sin NegativeSpaceComment
    public static decimal Cents(decimal amount) => Math.Round(amount, 2, MidpointRounding.AwayFromZero);

    public static decimal Percent(decimal share, decimal whole) => whole == 0 ? 0 : Cents(share / whole * 100);
}
