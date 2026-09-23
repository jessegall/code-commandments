namespace Shop.Stock;

// Reads the supplier's stock file that arrives each morning.
public static class StockImport
{
    public static string Read(string path)
    {
        try
        {
            return File.ReadAllText(path);
        }
        catch (IOException e)
        {
            // @sin WrappingWithoutCause
            throw new StockFileUnreadable($"Could not read the stock file: {e.Message}");
        }
    }

    // @fixed WrappingWithoutCause
    public static string ReadKeepingTheCause(string path)
    {
        try
        {
            return File.ReadAllText(path);
        }
        catch (IOException e)
        {
            throw new StockFileUnreadable("Could not read the stock file.", e);
        }
    }
}

public sealed class StockFileUnreadable : Exception
{
    public StockFileUnreadable(string message)
        : base(message)
    {
    }

    public StockFileUnreadable(string message, Exception cause)
        : base(message, cause)
    {
    }
}
