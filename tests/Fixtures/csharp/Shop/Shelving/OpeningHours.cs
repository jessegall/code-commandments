namespace Shop.Shelving;

public static class OpeningHours
{
    public static string Status(int opens, int closes, int hour)
    {
        if (OpeningWindow.Between(opens, closes).Covers(hour))
        {
            return "open";
        }

        // @sin RepeatedStaticFactory
        return $"closed, open {OpeningWindow.Between(opens, closes).Hours()} hours a day";
    }

    // @fixed RepeatedStaticFactory
    public static string StatusOnce(int opens, int closes, int hour)
    {
        var window = OpeningWindow.Between(opens, closes);
        if (window.Covers(hour))
        {
            return "open";
        }

        return $"closed, open {window.Hours()} hours a day";
    }
}
