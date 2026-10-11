using System.Collections.Generic;
using System.Linq;

namespace Shop.Stock;

public sealed record StockSource(string Id, int Quantity, bool Replenishes);

public static class HiddenStockSources
{
    public static readonly StockSource[] All = {
        new StockSource("shelf", 8, true),
        new StockSource("reserve", 30, false),
        new StockSource("returns", 2, false)
    };

    // @sin SideTableTrait
    private static readonly HashSet<string> Hidden = new() { "reserve", "returns" };

    public static StockSource[] Visible() => All.Where(source => source.Quantity > 0 && !Hidden.Contains(source.Id)).ToArray();
}
