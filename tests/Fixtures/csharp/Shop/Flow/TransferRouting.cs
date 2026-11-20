namespace Shop.Flow;

public sealed record TransferRoute(string Number, bool Debited, bool Accepted);

public sealed class TransferRouting(string depot, decimal cents, string[] cleared)
{
    private string office = depot;
    private decimal lastTotal = cents;
    private readonly string[] remaining = cleared;

    private static int CreditHeld(bool debited, bool accepted)
    {
        return debited && accepted ? 0 : 1;
    }

    public string Status(TransferRoute order)
    {
        // @sin ComputedBooleanArgument
        return CreditHeld(order.Debited, order.Accepted) == 0 ? "clear" : "pending";
    }

    public void SaveStatus(TransferRoute move, Dictionary<string, int> balance)
    {
        // @sin ComputedBooleanArgument
        balance.Add(move.Number, CreditHeld(move.Debited, move.Accepted));
    }

    private string Print(string number,
        // @sin UnusedParameter
        int endColumn)
    {
        var caption = new System.Text.StringBuilder(this.office);
        caption.Append(' ');
        caption.Append(number.Trim());
        return caption.Append(" / ").Append(this.lastTotal).ToString();
    }

    // @sin NarratedCommand
    public void Processes(decimal received)
    {
        this.lastTotal += received;
        Array.Sort(this.remaining);
    }

    // @sin HandRolledWither
    public TransferRouting WithOffice(string destination)
    {
        return new TransferRouting(destination, this.lastTotal, this.remaining);
    }

    // @sin KeyedLookupEnvy
    public Action<string> Track(TransferRoute paper, IReadOnlyDictionary<string, Action<string>> channels)
    {
        return channels[paper.Number];
    }

    // @sin ScratchStateRestore
    public string Inspect(string staging, string ticket)
    {
        string original = this.office;
        this.office = staging;
        try
        {
            return this.Print(ticket, this.remaining.Length);
        }
        finally
        {
            this.office = original;
        }
    }
}
