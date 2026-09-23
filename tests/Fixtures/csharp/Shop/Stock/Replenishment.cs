namespace Shop.Stock;

// Replenishment works out a shortfall in two places, and each method grew its own local function for
// it — the same arithmetic twice in one class.
public sealed class Replenishment(IReadOnlyDictionary<string, int> onHand, int minimum)
{
    public IReadOnlyList<string> ToOrder(IEnumerable<string> skus)
    {
        // @sin DuplicateMethod
        int Shortfall(string sku)
        {
            var have = onHand.TryGetValue(sku, out var count) ? count : 0;
            var missing = minimum - have;

            return missing > 0 ? missing + minimum / 4 : 0;
        }

        return skus.Where(sku => Shortfall(sku) > 0).ToList();
    }

    public int Units(IEnumerable<string> skus)
    {
        // @sin DuplicateMethod
        int Shortfall(string sku)
        {
            var have = onHand.TryGetValue(sku, out var count) ? count : 0;
            var missing = minimum - have;

            return missing > 0 ? missing + minimum / 4 : 0;
        }

        return skus.Sum(Shortfall);
    }
}
