using System.Text.Json;

namespace Shop.Stock;

// A stock snapshot is read back — and every failure along the way, a missing file and a bug alike, is
// caught and turned into "no snapshot".
public sealed class Snapshots(string folder)
{
    public Dictionary<string, int>? Load()
    {
        try
        {
            return JsonSerializer.Deserialize<Dictionary<string, int>>(File.ReadAllText(Path.Combine(folder, "stock.json")));
        }
        // @sin SwallowedException
        catch (Exception)
        {
            return null;
        }
    }

    public Dictionary<string, int> LoadOrEmpty()
    {
        try
        {
            return JsonSerializer.Deserialize<Dictionary<string, int>>(File.ReadAllText(Path.Combine(folder, "stock.json"))) ?? [];
        }
        // @fixed SwallowedException
        catch (FileNotFoundException)
        {
            return [];
        }
    }

    public Dictionary<string, int>? LoadIfReadable()
    {
        try
        {
            return JsonSerializer.Deserialize<Dictionary<string, int>>(File.ReadAllText(Path.Combine(folder, "stock.json")));
        }
        // @righteous SwallowedException
        catch (Exception error) when (error is JsonException or FileNotFoundException)
        {
            return null;
        }
    }
}
