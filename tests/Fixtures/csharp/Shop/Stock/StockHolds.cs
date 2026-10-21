namespace Shop.Stock;

// A hold written to the stock file, under a clause that catches the failure and throws it straight back:
// the try tells the reader something is handled here, and nothing is. The repair below it is the method
// with no clause at all, since the failure reaches the caller either way and now that is plain.
public sealed class StockHolds(string folder)
{
    public void Hold(string sku, int quantity)
    {
        try
        {
            File.AppendAllText(Path.Combine(folder, "holds.csv"), $"{sku};{quantity}\n");
        }
        // @sin RethrownUnchanged
        catch (IOException)
        {
            throw;
        }
    }

    // @fixed RethrownUnchanged
    public void HoldStock(string sku, int quantity)
    {
        File.AppendAllText(Path.Combine(folder, "holds.csv"), $"{sku};{quantity}\n");
    }
}
