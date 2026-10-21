namespace Shop.Picking;

// The stocktake count sheet, written for the desk that asked for it. The first clause names the failure and
// hands it back untouched; the second earns its clause by recording that the sheet was held before letting
// the failure travel; the third names a narrow failure so the broad clause under it never sees that one.
public sealed class CountSheets(string folder)
{
    public void Post(string desk, string sheet)
    {
        try
        {
            File.WriteAllText(Path.Combine(folder, $"{desk}.txt"), sheet);
        }
        // @sin RethrownUnchanged
        catch (UnauthorizedAccessException error)
        {
            throw error;
        }
    }

    // @righteous RethrownUnchanged
    public void PostCounted(string desk, string sheet)
    {
        try
        {
            File.WriteAllText(Path.Combine(folder, $"{desk}.txt"), sheet);
        }
        catch (UnauthorizedAccessException)
        {
            File.AppendAllText(Path.Combine(folder, "held.txt"), desk);

            throw;
        }
    }

    // @righteous RethrownUnchanged
    public void PostOrHold(string desk, string sheet)
    {
        try
        {
            File.WriteAllText(Path.Combine(folder, $"{desk}.txt"), sheet);
        }
        catch (OperationCanceledException)
        {
            throw;
        }
        catch (IOException)
        {
            File.AppendAllText(Path.Combine(folder, "held.txt"), desk);
        }
    }
}
