namespace Shop.Pricing;

public sealed record ReturnedLine(string Sku, double Refunded);

public sealed class CreditNote
{
    private decimal total;

    public void Credit(decimal amount) => total += Math.Round(amount, 2);

    public decimal Total => total;
}

public static class CreditNoteBuilder
{
    public static CreditNote For(IReadOnlyList<ReturnedLine> lines)
    {
        var note = new CreditNote();

        foreach (var line in lines)
        {
            // @sin ConvertedArgument
            note.Credit((decimal) line.Refunded);
        }

        return note;
    }

    public static CreditNote Single(ReturnedLine line)
    {
        var note = new CreditNote();
        // @sin ConvertedArgument
        note.Credit((decimal) line.Refunded);

        return note;
    }
}
