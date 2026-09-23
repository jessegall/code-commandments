namespace Shop.Stock;

public sealed record Shelf(string Sku, int OnHand, int Minimum, string? Supplier);

// Restocking walks every warehouse's shelves and decides, four choices deep, what to reorder — the
// decision that matters is buried under the ones that only filter.
public sealed class Restock(IReadOnlyDictionary<string, IReadOnlyList<Shelf>> warehouses)
{
    public IReadOnlyList<string> Reorders()
    {
        var reorders = new List<string>();

        foreach (var (warehouse, shelves) in warehouses)
        {
            foreach (var shelf in shelves)
            {
                if (shelf.OnHand < shelf.Minimum)
                {
                    // @sin DeepNesting
                    if (shelf.Supplier is not null)
                    {
                        reorders.Add($"{warehouse}: {shelf.Minimum - shelf.OnHand} x {shelf.Sku} from {shelf.Supplier}");
                    }
                }
            }
        }

        return reorders;
    }

    // @fixed DeepNesting
    public IReadOnlyList<string> ReordersFlat() =>
        warehouses
            .SelectMany(warehouse => warehouse.Value.Select(shelf => (Warehouse: warehouse.Key, Shelf: shelf)))
            .Where(entry => entry.Shelf.OnHand < entry.Shelf.Minimum && entry.Shelf.Supplier is not null)
            .Select(entry => Reorder(entry.Warehouse, entry.Shelf))
            .ToList();

    // @fixed DeepNesting
    private static string Reorder(string warehouse, Shelf shelf) =>
        $"{warehouse}: {shelf.Minimum - shelf.OnHand} x {shelf.Sku} from {shelf.Supplier}";
}
