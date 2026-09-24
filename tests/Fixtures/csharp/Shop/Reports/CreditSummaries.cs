namespace Shop.Reports;

using Shop.Pricing;

public sealed class CreditSummary
{
    public decimal Credited(IEnumerable<ReturnedLine> lines, CreditNote note)
    {
        foreach (var line in lines.Where(line => line.Refunded > 0))
        {
            // @sin ConvertedArgument
            note.Credit((decimal) line.Refunded);
        }

        return note.Total;
    }
}
