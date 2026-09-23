namespace Shop.Stock;

// An import row arrives as a dictionary and stays one — each use reads the same keys by name, a record
// nobody declared, with its field names repeated as strings wherever it is read.
public sealed class ImportRows
{
    public int Units(IReadOnlyDictionary<string, string> row)
    {
        // @sin DictionaryBag
        return int.Parse(row["units"]) * 2;
    }

    // @fixed DictionaryBag
    public int UnitsOf(ImportRow row) => row.Units * 2;

    // @righteous DictionaryBag
    public int OnHand(IReadOnlyDictionary<string, int> stock, string sku) => stock.GetValueOrDefault(sku);
}

// @fixed DictionaryBag
public sealed record ImportRow(string Sku, int Units)
{
    public static ImportRow From(IReadOnlyDictionary<string, string> row) => new(row["sku"], int.Parse(row["units"]));
}
