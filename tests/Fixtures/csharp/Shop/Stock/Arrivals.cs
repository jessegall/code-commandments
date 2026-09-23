namespace Shop.Stock;

// Arrivals are drained from a queue, and each one that carries a quantity is booked — inside an `if`
// that is the loop's whole body, without so much as braces around the loop.
public sealed class Arrivals(Queue<(string Sku, int Units)> queue, Dictionary<string, int> stock)
{
    public int Book()
    {
        var booked = 0;

        while (queue.TryDequeue(out var arrival))
            // @sin LoopWrappedInIf
            if (arrival.Units > 0)
            {
                stock[arrival.Sku] = stock.GetValueOrDefault(arrival.Sku) + arrival.Units;
                booked += arrival.Units;
            }

        return booked;
    }
}
