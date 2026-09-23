namespace Shop.Stock;

// A reservation counts what is held for an order — and an order nobody counted is reported as zero
// reserved, the same answer as an order that really reserved nothing.
public sealed class Reservations(Action<string, int> report)
{
    public void Report(string sku, int? held)
    {
        // @sin InventedDefault
        report(sku, held is null ? 0 : held.Value);
    }
}
