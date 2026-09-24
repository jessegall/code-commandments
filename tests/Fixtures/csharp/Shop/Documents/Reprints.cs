namespace Shop.Documents;

public sealed class Reprints
{
    private readonly List<string> queued = [];

    /// <summary>
    ///
    /// </summary>
    /// <param name="invoiceNumber"></param>
    /// <param name="copies"></param>
    /// <returns></returns>
    // @sin CeremonyDocblock
    public int Queue(string invoiceNumber, int copies)
    {
        for (var copy = 0; copy < copies; copy++)
        {
            queued.Add(invoiceNumber);
        }

        return queued.Count;
    }

    /// <summary>Queues a copy for each page the customer asked to see again, and says how many now wait.</summary>
    /// <param name="copies">How many copies the customer asked for; zero queues nothing.</param>
    // @fixed CeremonyDocblock
    public int QueueFor(string invoiceNumber, int copies)
    {
        queued.AddRange(Enumerable.Repeat(invoiceNumber, copies));

        return queued.Count;
    }

    /// <param name="invoiceNumber">The invoice number.</param>
    /// <exception cref="ArgumentException">The invoice was never issued.</exception>
    // @righteous CeremonyDocblock
    public void Cancel(string invoiceNumber)
    {
        if (!queued.Remove(invoiceNumber))
        {
            throw new ArgumentException(invoiceNumber);
        }
    }
}
