namespace Shop.Pricing;

// An order total split into what the shop keeps and what it owes in tax.
public static class VatSplits
{
    // @sin PositionalTupleReturn
    public static (decimal, decimal, string) Split(decimal gross, decimal rate, string currency) => (gross / (1 + rate), gross - gross / (1 + rate), currency);

    // @fixed PositionalTupleReturn
    public static VatSplit Divided(decimal gross, decimal rate) => new(gross / (1 + rate), gross - gross / (1 + rate));

    // @righteous PositionalTupleReturn
    public static (VatSplit, string) Labelled(decimal gross, decimal rate) => (Divided(gross, rate), $"{rate:P0} VAT");
}

// @fixed PositionalTupleReturn
public sealed record VatSplit(decimal Net, decimal Vat);
