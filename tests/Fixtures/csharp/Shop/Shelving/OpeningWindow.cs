namespace Shop.Shelving;

public sealed class OpeningWindow(int opens, int closes)
{
    public static OpeningWindow Between(int opens, int closes) => opens <= closes ? new(opens, closes) : new(closes, opens);

    public bool Covers(int hour) => hour >= opens && hour < closes;

    public int Hours() => closes - opens;
}
