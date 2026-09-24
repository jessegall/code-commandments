namespace Shop.Stock;

// Two listings page through stock the same way — a page, a size and a sort order handed along together —
// so the paging rules live in two places and a third listing will copy them.
public sealed class ShelfListing(IReadOnlyList<string> skus)
{
    // @sin DataClump
    public IReadOnlyList<string> Page(int page, int size, bool descending) =>
        (descending ? skus.OrderDescending() : skus.Order()).Skip(page * size).Take(size).ToList();
}

public sealed class BackorderListing(IReadOnlyList<(string Sku, int Missing)> backorders)
{
    // @sin DataClump
    public IReadOnlyList<string> Page(int page, int size, bool descending)
    {
        var ordered = descending ? backorders.OrderByDescending(entry => entry.Missing) : backorders.OrderBy(entry => entry.Missing);

        return ordered.Skip(page * size).Take(size).Select(entry => $"{entry.Sku}: {entry.Missing}").ToList();
    }
}

public sealed class Warehouse
{
    public int Page { get; }

    public int Size { get; }

    public bool Descending { get; }

    // @righteous DataClump
    public Warehouse(int page, int size, bool descending)
    {
        Page = page;
        Size = size;
        Descending = descending;
    }
}

public sealed class Depot
{
    public int Page { get; }

    public int Size { get; }

    public bool Descending { get; }

    // @righteous DataClump
    public Depot(int page, int size, bool descending)
    {
        Page = page;
        Size = size;
        Descending = descending;
    }
}
